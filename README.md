# WooCommerce WPML REST API Extension – Term Language Fix

**Version:** 1.0.2  
**Author:** NuoBiT Solutions, S.L.  
**Contributors:** Eric Antones <eantones@nuobit.com>  
**License:** GPLv3 or later  

## Description

A small WooCommerce + WPML compatibility helper that ensures **WooCommerce term** lookups by slug are restricted to the **currently active WPML language** when WordPress updates a term.

This avoids cases where `wp_update_term()` (or code paths that rely on it) may resolve the *wrong-language* term in multilingual stores.

## What it does

When WPML is active, this plugin:

- Hooks into the `terms_clauses` filter.
- Detects whether the current `get_terms()` call originates from `wp_update_term()` (via `debug_backtrace()`).
- Applies the restriction for WooCommerce product categories (`product_cat`) and attribute taxonomies (`pa_*`).
- Adds a JOIN to WPML's `icl_translations` table and filters by `tr.language_code = <current language>`.

## Why it exists

WooCommerce taxonomies (product categories and attributes like `pa_color`, `pa_size`, etc.) often use term slugs that can overlap across languages. During a term update, WordPress may query terms in a way that isn't language-aware. With WPML enabled, that can lead to:

- Updating the wrong translation of an attribute term
- Conflicting term resolutions when slugs match across languages

This helper forces the lookup to stay within the active WPML language for those specific update scenarios.

## Requirements

- WordPress
- WooCommerce (for `pa_*` attribute taxonomies)
- WPML (the filter is a no-op unless WPML is active)

## Installation

1. Place the plugin files in  
   `wp-content/plugins/woocommerce-wpml-rest-api-extension/`
2. Activate the plugin from **Plugins → Installed Plugins**
3. Ensure WPML is active (otherwise the plugin does nothing)

## GitHub Repository

[Visit NuoBiT's GitHub Repository](https://github.com/nuobit/woocommerce-wpml-api-rest-extension)
