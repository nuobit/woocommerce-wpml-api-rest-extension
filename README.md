# WooCommerce WPML REST API Extension – Term Language Fix

**Version:** 1.0.4  
**Author:** NuoBiT Solutions, S.L.  
**Contributors:** Eric Antones <eantones@nuobit.com>  
**License:** GPLv3 or later  

## Description

A small WooCommerce + WPML compatibility helper that ensures **WooCommerce term** lookups are restricted to the **currently active WPML language**.

This fixes the issue where WPML doesn't take into account the language parameter in REST API queries for categories and attributes, causing term queries to return results from all languages instead of the requested language.

## What it does

When WPML is active, this plugin:

- Hooks into the `terms_clauses` filter.
- Applies the restriction for WooCommerce product categories (`product_cat`) and attribute taxonomies (`pa_*`).
- Adds a JOIN to WPML's `icl_translations` table and filters by `tr.language_code = <current language>`.
- Skips filtering when language is set to 'all'.

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

## Installation

1. Place the plugin files in  
   `wp-content/plugins/woocommerce-wpml-rest-api-extension/`
2. Activate the plugin from **Plugins → Installed Plugins**
3. Ensure WPML is active (otherwise the plugin does nothing)

## GitHub Repository

[Visit NuoBiT's GitHub Repository](https://github.com/nuobit/woocommerce-wpml-api-rest-extension)
