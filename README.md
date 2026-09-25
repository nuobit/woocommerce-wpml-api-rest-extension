# WooCommerce WPML REST API Extension – Term Language Fix

**Version:** 1.0.7\
**Author:** NuoBiT Solutions, S.L.  
**Contributors:** Eric Antones \<<eantones@nuobit.com>\>  
**License:** [GPL-3.0-or-later](LICENSE)

## Description

A small WooCommerce + WPML compatibility helper that ensures **WooCommerce term** lookups are restricted to the **currently active WPML language**.

This fixes the issue where WPML doesn't take into account the language parameter in REST API queries for categories and attributes, causing term queries to return results from all languages instead of the requested language.

## What it does

When WPML is active, this plugin:

- Hooks into the `terms_clauses` filter **only during REST API requests** (`REST_REQUEST`).
- Applies the restriction only for WooCommerce product categories (`product_cat`) and attribute taxonomies (`pa_*`) that are **registered as translatable** in WPML.
- Restricts translated taxonomy rows to the active language with an `EXISTS` condition, preserving unrelated rows in mixed queries.
- Skips filtering when language is set to 'all'.
- Steps aside while WooCommerce Multilingual (WCML) suspends WPML's own `terms_clauses` filter around its cross-language operations (translation sync, term counts, attribute lookup table), so those internal lookups resolve the term ids of every language, as WCML intends.

## Changelog

### 1.0.7

- **Bugfix:** Step aside while WCML suspends WPML's `terms_clauses` filter. During a REST product save, WCML's translation sync looks up the translated category and attribute ids of every language with WPML's filter unhooked; the plugin kept restricting those lookups to the request language, so the ids of the other languages were not found and WooCommerce assigned the default category to the translations instead.
- Extend the [integration regression checks](tests/README.md) with a suspended pass in every mode.

### 1.0.6

- Preserve product types and other unrelated terms in mixed REST taxonomy queries, allowing native WooCommerce variable-parent synchronization after variation saves.
- Keep language restrictions on translated categories and attributes without adding an outer-query join.
- Add [integration regression checks](tests/README.md) for query scope and product-type cache priming.
- Align license notices with GPL-3.0-or-later and include the license text.

### 1.0.5

- **Bugfix:** Added `REST_REQUEST` guard to prevent the `terms_clauses` filter from running on frontend and admin pages. The filter was causing attribute dropdown selectors to disappear on product variation pages.
- **Bugfix:** Added `wpml_is_translated_taxonomy` check to skip non-translatable taxonomies. When a `pa_*` taxonomy is not set as translatable in WPML, its terms have no rows in `icl_translations`. The `INNER JOIN` then silently drops all terms, producing empty dropdowns.

### 1.0.4

- Updated plugin description and metadata.

### 1.0.3

- Initial release.

## Why it exists

This plugin addresses a bug in WPML where the REST API language filtering is not properly implemented for WooCommerce taxonomies. WPML has focused primarily on backend and frontend functionality, but appears to have limited support for REST API operations.

WooCommerce taxonomies (product categories and attributes like `pa_color`, `pa_size`, etc.) often use term slugs that can overlap across languages. When querying terms via the REST API, WPML doesn't properly filter by the language parameter, leading to:

- Updating the wrong translation of an attribute term
- Conflicting term resolutions when slugs match across languages
- Incorrect term associations in multilingual contexts
- API responses mixing terms from different languages

This helper forces term lookups to stay within the active WPML language for WooCommerce taxonomies, fixing what WPML should be handling natively.

**Note:** This is a workaround for WPML's API limitations. Ideally, WPML will address this in future updates, making this plugin unnecessary.

## Requirements

- WordPress
- WooCommerce (for `pa_*` attribute taxonomies)
- WPML (the filter is a no-op unless WPML is active)
- WooCommerce Multilingual (optional: the filter steps aside while WCML suspends WPML's term filters)

## Installation

1. Place the plugin files in  
   `wp-content/plugins/woocommerce-wpml-rest-api-extension/`
2. Activate the plugin from **Plugins → Installed Plugins**
3. Ensure WPML is active (otherwise the plugin does nothing)

## GitHub Repository

[Visit NuoBiT's GitHub Repository](https://github.com/nuobit/woocommerce-wpml-api-rest-extension)
