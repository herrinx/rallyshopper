# Herrecipes Plugin

WordPress plugin for recipe management with Kroger API integration.

## Features
- Full recipe CRUD (Create, Read, Update, Delete)
- Ingredient management with Kroger product linking
- Kroger API integration for grocery shopping
- Add all recipe ingredients to Kroger cart with one click
- Purchase tracking
- Staple item identification (items purchased 3+ times)

## Installation
1. Copy the `herrecipes` folder to `/wp-content/plugins/`
2. Activate the plugin in WordPress admin
3. Go to Herrecipes → Settings and enter your Kroger API credentials
4. Go to Herrecipes → Kroger Auth and connect your account
5. Start adding recipes!

## Kroger API Setup
1. Go to https://developer.kroger.com/
2. Create an application with these settings:
   - Name: Herrecipes
   - Redirect URI: `https://yoursite.com/wp-admin/admin.php?page=herrecipes-auth`
   - Client Type: Confidential
3. Copy Client ID and Client Secret to Settings page

## File Structure
```
herrecipes/
├── herrecipes.php                  # Main plugin file
├── includes/
│   ├── class-herrecipes.php        # Main plugin class
│   ├── class-database.php          # Database operations
│   ├── class-kroger-api.php        # Kroger API integration
│   ├── class-recipe.php            # Recipe post type & CRUD
│   ├── class-admin.php             # Admin interface
│   ├── class-ajax.php              # AJAX handlers
│   └── class-auth.php              # Kroger OAuth handling
├── assets/
│   ├── css/
│   │   └── admin.css               # Admin styles
│   └── js/
│       └── admin.js                # Admin JavaScript
└── templates/
    ├── recipe-list.php             # Recipe listing page
    ├── recipe-edit.php             # Add/edit recipe page
    ├── kroger-auth.php             # Kroger connection page
    ├── purchases.php               # Purchase history page
    ├── staples.php                 # Staple items page
    └── settings.php                # Settings page
```

## Database Tables
- `{prefix}herrecipes_recipes` - Recipe metadata
- `{prefix}herrecipes_ingredients` - Ingredient links to Kroger products
- `{prefix}herrecipes_purchases` - Purchase history tracking
- `{prefix}herrecipes_staples` - Frequently purchased items

## Version
1.0.0
