# Section Banner Template Suggestions

The Section Banner block supports Twig template suggestions, allowing you to override the default template based on **which section/tab triggered the banner display**, not the whole page context.

## Default Template

The default template is: `section-banner-block.html.twig`

## Template Suggestions

Template suggestions are automatically generated based on **the matched section/tab** that triggered the banner. This means you can create different templates for different banner sections, regardless of which page they appear on.

Drupal will look for templates in this order (most specific first):

### 1. Section-based Suggestions (Primary)

**Format:** `section-banner-block--section_[section-name].html.twig`

This is the **main way** to override templates - based on which section/tab is rendering the banner.

**Examples:**
- If banner target is `bundle:article`:
  - `section-banner-block--section-article.html.twig`
- If banner target is `/about`:
  - `section-banner-block--section-about.html.twig`
- If banner target is `view.articles`:
  - `section-banner-block--section-articles.html.twig`
- If banner target is `/news/*`:
  - `section-banner-block--section-news-wildcard.html.twig`

### 2. Bundle-based Suggestions

**Format:** `section-banner-block--bundle_[bundle-type].html.twig`

**Examples:**
- `section-banner-block--bundle-article.html.twig` - When banner targets `bundle:article` or `node.type.article`
- `section-banner-block--bundle-page.html.twig` - When banner targets `bundle:page`
- `section-banner-block--bundle-university.html.twig` - When banner targets `bundle:university`

### 3. View-based Suggestions

**Format:** `section-banner-block--view_[view-name].html.twig`

**Examples:**
- `section-banner-block--view-articles.html.twig` - When banner targets `view.articles`
- `section-banner-block--view-news.html.twig` - When banner targets `view.news`

### 4. Path-based Suggestions

**Format:** `section-banner-block--path_[sanitized-path].html.twig`

**Examples:**
- `section-banner-block--path-about.html.twig` - When banner targets `/about`
- `section-banner-block--path-contact-us.html.twig` - When banner targets `/contact-us`
- `section-banner-block--path-news-wildcard.html.twig` - When banner targets `/news/*`

### 5. CSS Class-based Suggestions

**Format:** `section-banner-block--[css-class].html.twig`

**Examples:**
- If you set CSS class `hero-banner` in the banner configuration:
  - `section-banner-block--hero-banner.html.twig`
- If you set CSS class `full-width`:
  - `section-banner-block--full-width.html.twig`

## How to Override Templates

### Step 1: Identify Which Section is Rendering

First, determine which section/tab is triggering your banner. Check your banner configuration at `admin/config/content/section-banner` to see the "Display Rules" (target sections).

**Common section types:**
- `bundle:article` - Targets article content type
- `/about` - Targets specific path
- `view.articles` - Targets a view
- `/news/*` - Targets path pattern

### Step 2: Copy the Default Template

Copy the default template from:
```
web/modules/custom/section_banner/templates/section-banner-block.html.twig
```

### Step 3: Place in Your Theme

Place your custom template in your theme's templates directory with the appropriate suggestion name:
```
web/themes/custom/your_theme/templates/section-banner-block--section-[section-name].html.twig
```

**Examples:**
- For `bundle:article`: `section-banner-block--section-article.html.twig`
- For `/about`: `section-banner-block--section-about.html.twig`
- For `view.articles`: `section-banner-block--section-articles.html.twig`

### Step 4: Customize the Template

Edit the template to match your needs. Available variables:

```twig
{#
  Available variables:
  - title: The banner title (with tokens replaced)
  - body: The processed banner body text (HTML)
  - image: The banner image URL
  - additional_class: Additional CSS class from banner configuration
  - matched_section: The original section that matched (e.g., 'bundle:article', '/about')
  - section_suggestion: Sanitized section name for template suggestions
  - css_class: Sanitized CSS class
#}
```

## Example Templates

### Example 1: Custom Template for Article Section

**File:** `web/themes/custom/your_theme/templates/section-banner-block--section-article.html.twig`

**Note:** This template will be used when a banner targets `bundle:article` or `node.type.article` section.

```twig
{#
/**
 * @file
 * Custom template for article banner.
 */
#}
<div class="section-banner section-banner--article {{ additional_class }}">
  {% if image %}
    <div class="section-banner-image-wrapper">
      <img src="{{ image }}" alt="{{ title }}" class="section-banner-image" />
    </div>
  {% endif %}
  <div class="section-banner-content">
    {% if title %}
      <h1 class="section-banner-title section-banner-title--article">{{ title }}</h1>
    {% endif %}
    {% if body %}
      <div class="section-banner-body section-banner-body--article">{{ body|raw }}</div>
    {% endif %}
  </div>
</div>
```

### Example 2: Custom Template for About Section

**File:** `web/themes/custom/your_theme/templates/section-banner-block--section-about.html.twig`

**Note:** This template will be used when a banner targets `/about` section.

```twig
{#
/**
 * @file
 * Custom template for about page banner.
 */
#}
<section class="hero-banner hero-banner--about {{ additional_class }}">
  <div class="hero-banner-background" style="background-image: url('{{ image }}');">
    <div class="hero-banner-overlay"></div>
  </div>
  <div class="hero-banner-content">
    <div class="container">
      {% if title %}
        <h1 class="hero-banner-title">{{ title }}</h1>
      {% endif %}
      {% if body %}
        <div class="hero-banner-text">{{ body|raw }}</div>
      {% endif %}
    </div>
  </div>
</section>
```

### Example 3: Custom Template Based on CSS Class

**File:** `web/themes/custom/your_theme/templates/section-banner-block--hero-banner.html.twig`

**Note:** This template will be used when a banner has CSS class `hero-banner` set in configuration.

```twig
{#
/**
 * @file
 * Custom template for hero banner style.
 */
#}
<div class="hero-section {{ additional_class }}">
  {% if image %}
    <div class="hero-background">
      <img src="{{ image }}" alt="{{ title }}" />
    </div>
  {% endif %}
  <div class="hero-content">
    {% if title %}
      <h1 class="hero-title">{{ title }}</h1>
    {% endif %}
    {% if body %}
      <div class="hero-description">{{ body|raw }}</div>
    {% endif %}
  </div>
</div>
```

## Debugging Template Suggestions

To see which template suggestions are available for a specific page:

1. Enable Twig debugging in your `sites/default/development.services.yml`:
```yaml
parameters:
  twig.config:
    debug: true
    auto_reload: true
    cache: false
```

2. Clear cache: `drush cr`

3. View the page source - you'll see HTML comments showing all available template suggestions:
```html
<!-- THEME DEBUG -->
<!-- THEME HOOK: 'section_banner_block' -->
<!-- FILE NAME SUGGESTIONS:
   * section-banner-block--entity-node-canonical.html.twig
   * section-banner-block--entity-node.html.twig
   * section-banner-block--bundle-article.html.twig
   * section-banner-block--article.html.twig
   x section-banner-block.html.twig
-->
```

The `x` marks the template that is actually being used.

## Priority Order

Template suggestions are checked in this order (most specific first):

1. `section-banner-block--section_[section-name].html.twig` (Primary - based on matched section)
2. `section-banner-block--bundle_[bundle].html.twig` (When section is bundle-based)
3. `section-banner-block--view_[view-name].html.twig` (When section is view-based)
4. `section-banner-block--path_[path].html.twig` (When section is path-based)
5. `section-banner-block--[css-class].html.twig` (CSS class from banner config)
6. `section-banner-block.html.twig` (default fallback)

## Best Practices

1. **Use bundle-based templates** for content type-specific styling
2. **Use path-based templates** for page-specific layouts
3. **Use CSS class-based templates** for reusable banner styles
4. **Keep templates DRY** - extend base template when possible
5. **Test template suggestions** using Twig debug mode

## Related Files

- Default template: `web/modules/custom/section_banner/templates/section-banner-block.html.twig`
- Theme hook: `web/modules/custom/section_banner/section_banner.module` (function `section_banner_theme_suggestions_section_banner_block`)
- Block plugin: `web/modules/custom/section_banner/src/Plugin/Block/SectionBannerBlock.php`

