<?php
/**
 * Copyright 2026 NuoBiT Solutions - Eric Antones <eantones@nuobit.com>
 * License GPL-3.0-or-later (https://www.gnu.org/licenses/gpl-3.0.html)
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
     * Restrict term queries to the active WPML language during REST API requests.
     *
     * Only applies to translatable WooCommerce taxonomies (product_cat, pa_*).
     * Rows from other taxonomies remain available in mixed queries, including
     * the product_type terms WooCommerce needs to identify variable products.
     *
     * @param array $clauses    SQL clauses for terms query.
     * @param array $taxonomies Taxonomies in query.
     * @param array $args       get_terms() args.
     *
     * @return array
     */
    public static function filter_terms_clauses( $clauses, $taxonomies, $args ) {
        global $wpdb;

        // Only apply during REST API requests.
        if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
            return $clauses;
        }

        // Only if lang defined and not 'all'.
        $lang = apply_filters( 'wpml_current_language', null );
        if ( ! $lang || $lang === 'all' ) {
            return $clauses;
        }

        // Only for taxonomies that are both relevant (product_cat, pa_*) AND
        // actually registered as translatable in WPML.
        $translatable_taxonomies = array_filter(
            (array) $taxonomies,
            static function ( $taxonomy ) {
                return is_string( $taxonomy ) && (
                    $taxonomy === 'product_cat' || 
                    strpos( $taxonomy, 'pa_' ) === 0
                ) && apply_filters( 'wpml_is_translated_taxonomy', false, $taxonomy );
            }
        );
        if ( empty( $translatable_taxonomies ) ) {
            return $clauses;
        }

        $table = $wpdb->prefix . 'icl_translations';

        $placeholders = implode( ', ', array_fill( 0, count( $translatable_taxonomies ), '%s' ) );

        // WordPress primes product relationships with a mixed taxonomy query.
        // Filter only translated rows; EXISTS also preserves row cardinality.
        $condition = $wpdb->prepare(
            " AND (tt.taxonomy NOT IN ({$placeholders}) OR EXISTS (
                SELECT 1 FROM {$table} AS wcwpml_translation
                WHERE wcwpml_translation.element_id = tt.term_taxonomy_id
                  AND wcwpml_translation.element_type = CONCAT('tax_', tt.taxonomy)
                  AND wcwpml_translation.language_code = %s
            ))",
            array_merge( array_values( $translatable_taxonomies ), [ $lang ] )
        );

        if ( strpos( $clauses['where'], $condition ) === false ) {
            $clauses['where'] .= $condition;
        }

        return $clauses;
    }
}
