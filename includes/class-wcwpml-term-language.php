<?php
/**
 * Copyright 2025 NuoBiT Solutions - Eric Antones <eantones@nuobit.com>
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

        // Only if WPML is active.
        if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
            return $clauses;
        }

        // Detect if this get_terms() was called from wp_update_term().
        $stack = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
        $in_wp_update_term = false;
        foreach ( $stack as $frame ) {
            if ( ! empty( $frame['function'] ) && $frame['function'] === 'wp_update_term' ) {
                $in_wp_update_term = true;
                break;
            }
        }
        if ( ! $in_wp_update_term ) {
            // Guaranteed: only wp_update_term calls pass through.
            return $clauses;
        }

        // Only for WooCommerce attribute taxonomies (pa_*).
        $attribute_taxonomies = array_filter(
            (array) $taxonomies,
            static function ( $taxonomy ) {
                // If you're not on PHP 8+, replace str_starts_with with strpos === 0.
                return is_string( $taxonomy ) && str_starts_with( $taxonomy, 'pa_' );
            }
        );
        if ( empty( $attribute_taxonomies ) ) {
            return $clauses;
        }

        // Only if lang defined.
        $lang = apply_filters( 'wpml_current_language', null );
        if ( ! $lang ) {
            return $clauses;
        }

        $table = $wpdb->prefix . 'icl_translations';

        // This is the exact JOIN we care about in *our* code.
        $join = " INNER JOIN {$table} AS tr
        ON tt.term_taxonomy_id = tr.element_id
        AND tr.element_type = CONCAT('tax_', tt.taxonomy)";

        // Has *our* exact join already been added?
        if ( strpos( $clauses['join'], $join ) === false ) {
            // 1) Add our join.
            $clauses['join'] .= $join;

            // 2) Add our WHERE using alias `tr` (safe: we just added it).
            $clauses['where'] .= $wpdb->prepare(
                ' AND tr.language_code = %s',
                $lang
            );
        }

        return $clauses;
    }
}