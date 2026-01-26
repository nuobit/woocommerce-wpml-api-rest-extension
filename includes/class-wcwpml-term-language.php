<?php
/**
 * Copyright 2026 NuoBiT Solutions - Eric Antones <eantones@nuobit.com>
 * License AGPL-3.0 or later (https://www.gnu.org/licenses/agpl)
 * 
 * @package WooCommerce_WPML_REST_API_Extension
 */

defined( 'ABSPATH' ) || exit;


class WCWPML_Term_Language {

    /**
     * Bootstrap the hooks.
     */
    public static function init() {
        add_filter( 'terms_clauses', [ static::class, 'filter_terms_clauses' ], 10, 3 );
    }

    /**
     * Restrict slug lookups to the active language when our flag is present.
     *
     * @param array $clauses    SQL clauses for terms query.
     * @param array $taxonomies Taxonomies in query.
     * @param array $args       get_terms() args.
     *
     * @return array
     */
    public static function filter_terms_clauses( $clauses, $taxonomies, $args ) {
        global $wpdb;

        // Only if lang defined and not 'all'.
        $lang = apply_filters( 'wpml_current_language', null );
        if ( ! $lang || $lang === 'all' ) {
            return $clauses;
        }

        // Only for taxonomies: Categories (product_cat) and Attribute values (pa_*).
        $attribute_taxonomies = array_filter(
            (array) $taxonomies,
            static function ( $taxonomy ) {
                return is_string( $taxonomy ) && (
                    $taxonomy === 'product_cat' || 
                    strpos( $taxonomy, 'pa_' ) === 0
                );
            }
        );
        if ( empty( $attribute_taxonomies ) ) {
            return $clauses;
        }

        $table = $wpdb->prefix . 'icl_translations';

        // This is the exact JOIN we care about in *our* code.
        $join = " INNER JOIN {$table} AS wpmltr
        ON tt.term_taxonomy_id = wpmltr.element_id
        AND wpmltr.element_type = CONCAT('tax_', tt.taxonomy)";

        // Has *our* exact join already been added?
        if ( strpos( $clauses['join'], $join ) === false ) {
            // 1) Add our join.
            $clauses['join'] .= $join;

            // 2) Add our WHERE using alias `wpmltr` (safe: we just added it).
            $clauses['where'] .= $wpdb->prepare(
                ' AND wpmltr.language_code = %s',
                $lang
            );
        }

        return $clauses;
    }
}