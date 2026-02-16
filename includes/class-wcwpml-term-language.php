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
     * Restrict term queries to the active WPML language during REST API requests.
     *
     * Only applies to translatable WooCommerce taxonomies (product_cat, pa_*).
     * Non-translatable taxonomies are skipped to avoid INNER JOIN on missing
     * icl_translations rows which would silently drop all terms.
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
        // actually registered as translatable in WPML. If a taxonomy is not
        // translatable, its terms have no rows in icl_translations and an
        // INNER JOIN would silently drop every term.
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