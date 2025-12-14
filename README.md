# Section Banner

Provides section-wise banners that can be displayed on any page based on path patterns, Views machine names, content types, or route names. Supports multi-language content with automatic language fallback.

## Features

- **Flexible Targeting**: Display banners based on:
  - Specific paths (e.g., `/about`, `/contact`)
  - Wildcard paths (e.g., `/news/*`, `/node/*`)
  - Content types (e.g., `bundle:article`, `node.type.page`)
  - Views machine names (e.g., `articles`, `view.news`)
  - View paths (e.g., `/articles`, `/news`)
  - Route names (e.g., `section_banner.settings`)
- **Multi-language Support**: Configure banner content for each active language with automatic fallback
- **Token Support**: Use Drupal tokens in banner titles and content (e.g., `[node:title]`, `[current-user:name]`)
- **Template Suggestions**: Override templates based on matched sections, bundles, views, paths, or CSS classes
- **Image Support**: Upload banner images that are shared across all languages
- **Custom CSS Classes**: Add custom CSS classes for styling flexibility
- **Cache Optimization**: Proper cache contexts and tags for optimal performance

## Requirements

- Drupal 10 or 11
- Core modules: Block, File, Image, Filter

## Installation

1. Place the module in `modules/custom/section_banner`
2. Enable the module via Drush:
   ```bash
   drush en section_banner
   ```
   Or via the admin interface: Extend > Install

3. Grant the "Administer Section Banner" permission to appropriate roles

## Configuration

1. Navigate to **Configuration > Content authoring > Section Banner** (`/admin/config/content/section-banner`)
2. Select the language you want to edit
3. Configure your banners:
   - **Banner Content**: Title and body text (supports tokens)
   - **Banner Image**: Upload an image (shared across languages)
   - **Display Rules**: Specify where the banner should appear
   - **Styling**: Add custom CSS classes

## Usage Examples

### Target Specific Pages

```
/about
/contact
/admin/config/content/section-banner
```

### Target Content Types

```
bundle:article
node.type.page
```

### Target Views

```
articles
view.news
/articles
/news
```

### Target Routes

```
section_banner.settings
entity.node.canonical
```

### Wildcard Patterns

```
/news/*
/node/*
```

### Exclusions

```
except:bundle:page
except:node.type.article
```

## Token Examples

The module supports Drupal tokens in banner titles and content:

- `[node:title]` - Current node title
- `[node:url]` - Current node URL
- `[node:author:name]` - Node author name
- `[current-user:name]` - Current user name
- `[current-user:mail]` - Current user email
- `[site:name]` - Site name
- `[site:slogan]` - Site slogan
- `[date:custom:Y-m-d]` - Current date (custom format)

See the Token browser in the form for all available tokens.

## Template Customization

The module provides template suggestions based on the matched section:

- `section-banner-block--section-{section}.html.twig` - Based on matched section
- `section-banner-block--bundle-{bundle}.html.twig` - Based on content type
- `section-banner-block--view-{view}.html.twig` - Based on view name
- `section-banner-block--path-{path}.html.twig` - Based on path
- `section-banner-block--{css-class}.html.twig` - Based on CSS class

See `TEMPLATE_SUGGESTIONS.md` for detailed documentation.

## Block Placement

1. Navigate to **Structure > Block layout**
2. Find "Section Banner Block" in the available blocks
3. Place it in the desired region
4. Configure block visibility settings if needed

## Multi-language Support

1. Configure banners for each active language
2. The module automatically displays content in the current language
3. Falls back to default language if translation is missing
4. Falls back to first available translation if default is missing

## API

### Programmatic Banner Retrieval

```php
$banners = \Drupal::state()->get('section_banner.banners', []);
```

### Cache Invalidation

```php
\Drupal::service('cache_tags.invalidator')->invalidateTags(['section_banner:banners']);
```

## Troubleshooting

### Banner Not Displaying

1. Check that the block is placed in a region
2. Verify the target sections match the current page
3. Check block visibility settings
4. Clear cache: `drush cr`

### Language Not Showing

1. Ensure the language is enabled in Drupal
2. Configure banner content for that language
3. Check language fallback settings

