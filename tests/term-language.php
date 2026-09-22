<?php
/**
 * Copyright 2026 NuoBiT Solutions - Eric Antones <eantones@nuobit.com>
 * License AGPL-3.0 or later (https://www.gnu.org/licenses/agpl)
 *
 * Read-only integration regression; run with wp eval-file (see tests/README.md).
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || count( $args ) !== 2 ) {
    throw new RuntimeException( 'Expected WP-CLI, a fixture manifest and a request mode.' );
}
$fixture = json_decode( file_get_contents( $args[0] ), true );
$mode = $args[1];
if ( ! is_array( $fixture ) || ! in_array( $mode, [ 'rest', 'non-rest', 'rest-false' ], true ) ) {
    throw new RuntimeException( 'Invalid fixture or request mode.' );
}
if ( home_url() !== $fixture['site_url'] || DB_NAME !== $fixture['database'] ) {
    throw new RuntimeException( 'Unexpected WordPress site or database.' );
}
if ( wp_using_ext_object_cache() || defined( 'REST_REQUEST' ) ) {
    throw new RuntimeException( 'Use a fresh CLI process without persistent object caching.' );
}
if ( empty( $fixture['sku_prefix'] ) || empty( $fixture['variable_products'] ) ) {
    throw new RuntimeException( 'An explicit synthetic fixture is required.' );
}
foreach ( $fixture['variable_products'] as $product_id ) {
    if ( strpos( get_post_field( 'post_title', $product_id ), $fixture['sku_prefix'] ) !== 0 ) {
        throw new RuntimeException( 'Product is outside the synthetic fixture.' );
    }
}
if ( $mode !== 'non-rest' ) {
    define( 'REST_REQUEST', $mode === 'rest' );
}
$callback = [ 'WCWPML_Term_Language', 'filter_terms_clauses' ];
if ( ! is_callable( $callback ) || has_filter( 'terms_clauses', $callback ) === false ) {
    throw new RuntimeException( 'The plugin must be active.' );
}

$checks = [];
$check = static function ( $name, $actual, $expected ) use ( &$checks ) {
    $checks[] = [ 'name' => $name, 'passed' => $actual === $expected ];
};
$ids = static function ( $values ) {
    $values = array_map( 'intval', $values );
    sort( $values, SORT_NUMERIC );
    return $values;
};
$attribute = $fixture['attribute_taxonomy'];
$type_ids = [ (int) $fixture['product_type_term_id'] ];
$all_categories = [];
$all_attributes = [];
foreach ( $fixture['terms'] as $terms ) {
    $all_categories = array_merge( $all_categories, $terms['product_cat'] );
    $all_attributes = array_merge( $all_attributes, $terms[$attribute] );
}
$all_ids = array_merge( $type_ids, $all_categories, $all_attributes );

// Expected IDs are fixture inputs, independent of the filter's SQL.
$query = static function ( $taxonomies, $empty = false, $outer_alias = false ) use ( $all_ids, $callback ) {
    global $wpdb;
    $id_slots = implode( ', ', array_fill( 0, count( $all_ids ), '%d' ) );
    $taxonomy_slots = implode( ', ', array_fill( 0, count( $taxonomies ), '%s' ) );
    $clauses = [
        'fields' => 't.term_id',
        'join' => " INNER JOIN {$wpdb->term_taxonomy} AS tt ON tt.term_id = t.term_id",
        'where' => $wpdb->prepare(
            "t.term_id IN ({$id_slots}) AND tt.taxonomy IN ({$taxonomy_slots})",
            array_merge( $all_ids, $taxonomies )
        ),
        'distinct' => '', 'orderby' => 't.term_id', 'order' => 'ASC', 'limits' => '',
    ];
    if ( $empty ) {
        $clauses['where'] .= ' AND 1 = 0';
    }
    if ( $outer_alias ) {
        $clauses['join'] .= " LEFT JOIN {$wpdb->terms} AS wpmltr ON wpmltr.term_id = t.term_id";
    }
    $filtered = call_user_func( $callback, $clauses, $taxonomies, [] );
    // SQL failures become failed assertions without dumping database details.
    $old_suppression = $wpdb->suppress_errors( true );
    try {
        $result = $wpdb->get_col(
            "SELECT {$filtered['fields']} FROM {$wpdb->terms} AS t {$filtered['join']}"
            . " WHERE {$filtered['where']} ORDER BY {$filtered['orderby']} {$filtered['order']}"
        );
        $valid = $wpdb->last_error === '';
    } finally {
        $wpdb->suppress_errors( $old_suppression );
    }
    return [
        'ids' => $valid ? array_map( 'intval', $result ) : null,
        'idempotent' => call_user_func( $callback, $filtered, $taxonomies, [] ) === $filtered,
        'unchanged' => $filtered === $clauses,
    ];
};

foreach ( $fixture['variable_products'] as $language => $product_id ) {
    do_action( 'wpml_switch_language', $language );
    $selected_language = $language;
    $language_filter = static function () use ( &$selected_language ) { return $selected_language; };
    add_filter( 'wpml_current_language', $language_filter, PHP_INT_MAX );
    try {
        $terms = $fixture['terms'][$language];
        $categories = $mode === 'rest' ? $terms['product_cat'] : $all_categories;
        $attributes = $mode === 'rest' ? $terms[$attribute] : $all_attributes;
        $cases = [
            'category' => [ [ 'product_cat' ], $categories ],
            'attribute' => [ [ $attribute ], $attributes ],
            'translated' => [ [ 'product_cat', $attribute ], array_merge( $categories, $attributes ) ],
            'type' => [ [ 'product_type' ], $type_ids ],
            'mixed' => [ [ 'product_cat', $attribute, 'product_type' ], array_merge( $categories, $attributes, $type_ids ) ],
        ];
        foreach ( $cases as $name => $case ) {
            $result = $query( $case[0] );
            $check( "{$language}:{$name}:terms", $result['ids'], $ids( $case[1] ) );
            $check( "{$language}:{$name}:idempotence", $result['idempotent'], true );
            if ( $mode !== 'rest' ) {
                $check( "{$language}:{$name}:no-op", $result['unchanged'], true );
            }
        }
        $check( "{$language}:empty", $query( [ 'product_cat', 'product_type' ], true )['ids'], [] );
        $check( "{$language}:outer-alias", $query( [ 'product_cat', 'product_type' ], false, true )['ids'], $ids( array_merge( $categories, $type_ids ) ) );

        $untranslated_attribute = static function ( $translated, $taxonomy ) use ( $attribute ) {
            return $taxonomy === $attribute ? false : $translated;
        };
        add_filter( 'wpml_is_translated_taxonomy', $untranslated_attribute, PHP_INT_MAX, 2 );
        try {
            $result = $query( [ 'product_cat', $attribute, 'product_type' ] );
            $check( "{$language}:untranslated-attribute", $result['ids'], $ids( array_merge( $categories, $all_attributes, $type_ids ) ) );
        } finally {
            remove_filter( 'wpml_is_translated_taxonomy', $untranslated_attribute, PHP_INT_MAX );
        }
        foreach ( [ null, false, '', 'all' ] as $no_language ) {
            $selected_language = $no_language;
            $result = $query( [ 'product_cat', $attribute, 'product_type' ] );
            $check( "{$language}:language-no-op:" . var_export( $no_language, true ), $result['unchanged'], true );
        }
        $selected_language = $language;

        // Exercise normal cache priming; never force the product class or sync.
        foreach ( get_object_taxonomies( 'product' ) as $taxonomy ) {
            wp_cache_delete( $product_id, $taxonomy . '_relationships' );
        }
        $type_key = WC_Cache_Helper::get_cache_prefix( 'product_' . $product_id ) . '_type_' . $product_id;
        wp_cache_delete( $type_key, 'products' );
        update_object_term_cache( [ $product_id ], 'product' );
        $product = wc_get_product( $product_id );
        $check( "{$language}:cached-product-type", $product->get_type(), 'variable' );
        $check( "{$language}:cached-product-class", get_class( $product ), 'WC_Product_Variable' );
    } finally {
        remove_filter( 'wpml_current_language', $language_filter, PHP_INT_MAX );
    }
}
$failures = array_values( array_filter( $checks, static function ( $item ) { return ! $item['passed']; } ) );
echo wp_json_encode( [ 'mode' => $mode, 'checks' => count( $checks ), 'failures' => $failures ], JSON_PRETTY_PRINT ), "\n";
if ( $failures ) {
    WP_CLI::halt( 1 );
}
