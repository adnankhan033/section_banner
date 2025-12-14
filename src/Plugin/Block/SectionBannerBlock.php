<?php

namespace Drupal\section_banner\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Utility\Token;

/**
 * Provides a 'Section Banner Block' block.
 *
 * Displays configured banners based on current page context (route, path,
 * content type, view, etc.) with multi-language support.
 */
#[Block(
  id: "section_banner_block",
  admin_label: new TranslatableMarkup("Section Banner Block"),
  category: new TranslatableMarkup("Custom")
)]
class SectionBannerBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The current path stack.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Creates a SectionBannerBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The alias manager.
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   The current path stack.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, StateInterface $state, RouteMatchInterface $route_match, AliasManagerInterface $alias_manager, CurrentPathStack $current_path, LanguageManagerInterface $language_manager, Token $token) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->state = $state;
    $this->routeMatch = $route_match;
    $this->aliasManager = $alias_manager;
    $this->currentPath = $current_path;
    $this->languageManager = $language_manager;
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('state'),
      $container->get('current_route_match'),
      $container->get('path_alias.manager'),
      $container->get('path.current'),
      $container->get('language_manager'),
      $container->get('token')
    );
  }

  /**
   * Get banner content for current language with fallback to default.
   *
   * @param array $banner
   *   The banner data array.
   *
   * @return array
   *   Array with 'title' and 'body' keys.
   */
  protected function getBannerContent(array $banner) {
    $current_language = $this->languageManager->getCurrentLanguage()->getId();
    $default_language = $this->languageManager->getDefaultLanguage()->getId();
    $translations = $banner['translations'] ?? [];
    
    // Try current language first.
    if (isset($translations[$current_language])) {
      $translation = $translations[$current_language];
      return [
        'title' => $translation['title'] ?? '',
        'body' => $translation['body'] ?? ['value' => '', 'format' => 'basic_html'],
      ];
    }
    
    // Fallback to default language.
    if (isset($translations[$default_language])) {
      $translation = $translations[$default_language];
      return [
        'title' => $translation['title'] ?? '',
        'body' => $translation['body'] ?? ['value' => '', 'format' => 'basic_html'],
      ];
    }
    
    // Fallback to first available translation.
    if (!empty($translations)) {
      $first_translation = reset($translations);
      return [
        'title' => $first_translation['title'] ?? '',
        'body' => $first_translation['body'] ?? ['value' => '', 'format' => 'basic_html'],
      ];
    }
    
    // No translations available, return empty.
    return [
      'title' => '',
      'body' => ['value' => '', 'format' => 'basic_html'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $banners = $this->state->get('section_banner.banners', []);

    if (empty($banners)) {
      return [];
    }

    // Get current path and route.
    $current_path = $this->currentPath->getPath();
    $current_route = $this->routeMatch->getRouteName();
    
    // Get aliased path (if any).
    $aliased_path = $this->aliasManager->getAliasByPath($current_path);
    if ($aliased_path === $current_path) {
      // No alias exists.
      $aliased_path = NULL;
    }

    // Get current node and bundle if on a node page.
    $current_node = NULL;
    $current_bundle = NULL;
    if ($current_route === 'entity.node.canonical') {
      $current_node = $this->routeMatch->getParameter('node');
      if ($current_node && $current_node->getEntityTypeId() === 'node') {
        $current_bundle = $current_node->bundle();
      }
    }

    // Find matching banner and track which section matched.
    $matching_banner = NULL;
    $matched_section = NULL;
    foreach ($banners as $banner) {
      if (empty($banner['target_sections']) || !is_array($banner['target_sections'])) {
        continue;
      }

      $banner_excluded = FALSE;
      $matched = FALSE;

      // First pass: check for exclusions
      foreach ($banner['target_sections'] as $target) {
        $target = trim($target);
        if (empty($target)) {
          continue;
        }

        // Check for bundle exclusion: except:bundle:page or except:node.type.page
        if (strpos($target, 'except:') === 0) {
          $exclude_target = substr($target, 7); // Remove "except:"
          // Check if it's except:bundle: or except:node.type.
          if (strpos($exclude_target, 'bundle:') === 0) {
            $exclude_bundle = substr($exclude_target, 7);
          }
          elseif (strpos($exclude_target, 'node.type.') === 0) {
            $exclude_bundle = substr($exclude_target, 10);
          }
          else {
            $exclude_bundle = $exclude_target;
          }
          
          // If we're on a node page and bundle matches exclusion, exclude this banner.
          if ($current_bundle && $current_bundle === $exclude_bundle) {
            $banner_excluded = TRUE;
            break; // Skip this banner entirely
          }
        }
      }

      // If banner is excluded, skip to next banner
      if ($banner_excluded) {
        continue;
      }

      // Second pass: check for matches
      foreach ($banner['target_sections'] as $target) {
        $target = trim($target);
        if (empty($target)) {
          continue;
        }

        // Skip exclusion targets in match checking
        if (strpos($target, 'except:') === 0) {
          continue;
        }

        // Check if target is a route name (e.g., "section_banner.settings").
        // This should be checked early before other pattern matching.
        if ($current_route === $target && strpos($target, '.') !== FALSE) {
          $matched = TRUE;
          $matched_section = $target;
        }
        // Check for bundle matching: bundle:article or node.type.article
        elseif (strpos($target, 'bundle:') === 0) {
          $bundle_name = substr($target, 7);
          if ($current_bundle && $current_bundle === $bundle_name) {
            $matched = TRUE;
            $matched_section = $target;
          }
        }
        elseif (strpos($target, 'node.type.') === 0) {
          $bundle_name = substr($target, 10);
          if ($current_bundle && $current_bundle === $bundle_name) {
            $matched = TRUE;
            $matched_section = $target;
          }
        }
        // Check if it's "all nodes" pattern: /node/* or node/*
        // This will also be caught by wildcard pattern matching below, but check route first for efficiency
        if ($target === '/node/*' || $target === 'node/*' || $target === '/node/*/' || $target === 'node/*/' || preg_match('/^\/?node\/\*\/?$/', $target)) {
          if ($current_route === 'entity.node.canonical' && $current_node) {
            $matched = TRUE;
            $matched_section = $target;
          }
        }
        // Check if it's a Views machine name or path.
        // Support formats: "view.articles", "articles", "/articles", "/news"
        elseif (strpos($target, 'view.') === 0) {
          // Target is "view.articles" format - extract view name.
          $view_name = substr($target, 5);
          // Match Views routes like "view.articles.page_1", "view.articles.page_2", etc.
          if (strpos($current_route, 'view.' . $view_name . '.') === 0) {
            $matched = TRUE;
            $matched_section = $target;
          }
        }
        elseif (strpos($current_route, 'view.') === 0) {
          // Current route is a view route, check if target matches the view name.
          // Extract view name from route (e.g., "view.articles.page_1" -> "articles").
          $route_parts = explode('.', $current_route);
          if (count($route_parts) >= 2 && $route_parts[0] === 'view' && $route_parts[1] === $target) {
            $matched = TRUE;
            $matched_section = $target;
          }
        }
        // Check if target is a view path (e.g., "/articles", "/news").
        // If we're on a view route and the path matches, it's a match.
        // The path matching logic below will also catch this, but we check here
        // specifically for view routes to ensure proper matching.
        elseif (strpos($target, '/') === 0 && strpos($current_route, 'view.') === 0) {
          // If current path or aliased path matches the target, it's a match.
          $target_normalized = rtrim($target, '/');
          $current_normalized = rtrim($current_path, '/');
          $alias_normalized = $aliased_path ? rtrim($aliased_path, '/') : '';
          
          if ($target_normalized === $current_normalized || 
              ($alias_normalized && $target_normalized === $alias_normalized)) {
            $matched = TRUE;
            $matched_section = $target;
          }
        }
        // Check if target is a view machine name without "view." prefix (e.g., "articles").
        // This should match if current route is a view route with that machine name.
        elseif (!strpos($target, ':') && !strpos($target, '/') && !strpos($target, '*')) {
          // Potential view machine name - check if current route matches.
          if (strpos($current_route, 'view.') === 0) {
            $route_parts = explode('.', $current_route);
            if (count($route_parts) >= 2 && $route_parts[1] === $target) {
              $matched = TRUE;
              $matched_section = $target;
            }
          }
        }

        // If not matched yet, check if it's a path pattern.
        if (!$matched) {
          // Normalize target path (ensure it starts with /).
          $normalized_target = $target;
          if (strpos($normalized_target, '/') !== 0) {
            $normalized_target = '/' . $normalized_target;
          }
          
          // Normalize paths for comparison (ensure they start with /).
          $normalized_current = $current_path;
          if (strpos($normalized_current, '/') !== 0) {
            $normalized_current = '/' . $normalized_current;
          }
          
          // First check for exact match (fastest).
          if ($normalized_target === $normalized_current) {
            $matched = TRUE;
            $matched_section = $target;
          }
          elseif ($aliased_path) {
            $normalized_alias = $aliased_path;
            if (strpos($normalized_alias, '/') !== 0) {
              $normalized_alias = '/' . $normalized_alias;
            }
            if ($normalized_target === $normalized_alias) {
              $matched = TRUE;
              $matched_section = $target;
            }
          }
          
          // If no exact match and target contains wildcard, use regex.
          if (!$matched && strpos($normalized_target, '*') !== FALSE) {
            // Convert wildcard pattern to regex, escaping special regex chars except *.
            $pattern = preg_quote($normalized_target, '/');
            $pattern = str_replace(['\*', '\/'], ['.*', '/'], $pattern);
            $pattern = '/^' . $pattern . '$/';
            
            // Check against internal path (e.g., /node/123).
            if (preg_match($pattern, $normalized_current)) {
              $matched = TRUE;
              $matched_section = $target;
            }
            // Check against aliased path if it exists (e.g., /about).
            elseif ($aliased_path) {
              $normalized_alias = $aliased_path;
              if (strpos($normalized_alias, '/') !== 0) {
                $normalized_alias = '/' . $normalized_alias;
              }
              if (preg_match($pattern, $normalized_alias)) {
                $matched = TRUE;
                $matched_section = $target;
              }
            }
          }
        }

        if ($matched) {
          $matching_banner = $banner;
          break 2;
        }
      }
    }

    if (!$matching_banner) {
      return [];
    }

    // Get banner content for current language with fallback.
    $banner_content = $this->getBannerContent($matching_banner);
    $title = $banner_content['title'];
    $body_data = $banner_content['body'];

    // Get current node for token replacement.
    $current_node = NULL;
    if ($this->routeMatch->getRouteName() === 'entity.node.canonical') {
      $current_node = $this->routeMatch->getParameter('node');
    }

    // Prepare token data.
    $token_data = [];
    if ($current_node) {
      $token_data['node'] = $current_node;
    }
    $token_data['current-page'] = $this->routeMatch->getRouteObject();

    // Process tokens in title.
    if (!empty($title)) {
      $title = $this->token->replace($title, $token_data, ['clear' => TRUE]);
    }

    // Process image (shared across all languages).
    $image_url = NULL;
    $file = NULL;
    if (!empty($matching_banner['image'])) {
      $file = File::load($matching_banner['image']);
      if ($file) {
        $image_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
      }
    }

    // Process body text and tokens.
    $body = NULL;
    if (!empty($body_data['value'])) {
      // Replace tokens in body text before processing.
      $body_text = $this->token->replace($body_data['value'], $token_data, ['clear' => TRUE]);
      $body = [
        '#type' => 'processed_text',
        '#text' => $body_text,
        '#format' => $body_data['format'] ?? 'basic_html',
      ];
      $body = \Drupal::service('renderer')->renderPlain($body);
    }

    // Get cache tags for proper cache invalidation.
    // State doesn't have cache tags, so we use a custom cache tag.
    $cache_tags = ['section_banner:banners'];
    // Add file cache tag if image exists.
    if ($file) {
      $cache_tags = Cache::mergeTags($cache_tags, $file->getCacheTags());
    }

    // Prepare context for template suggestions based on matched section.
    $route_name = $this->routeMatch->getRouteName();
    $current_bundle = NULL;
    if ($current_node) {
      $current_bundle = $current_node->bundle();
    }
    
    // Get CSS class and create a sanitized version for template suggestions.
    $css_class = $matching_banner['css_class'] ?? '';
    $css_class_suggestion = '';
    if (!empty($css_class)) {
      // Sanitize CSS class for use in template name (remove special chars).
      $css_class_suggestion = preg_replace('/[^a-z0-9_-]/i', '_', strtolower($css_class));
    }
    
    // Process matched section for template suggestions.
    $section_suggestion = NULL;
    if ($matched_section) {
      // Sanitize the matched section for use in template name.
      $section_suggestion = preg_replace('/[^a-z0-9_-]/i', '_', strtolower($matched_section));
      // Remove common prefixes for cleaner template names.
      $section_suggestion = str_replace(['bundle_', 'node_type_', 'view_'], '', $section_suggestion);
      // Remove leading/trailing slashes and wildcards.
      $section_suggestion = trim($section_suggestion, '/_*');
    }

    $build = [
      '#theme' => 'section_banner_block',
      '#title' => !empty($title) ? $title : NULL,
      '#body' => $body,
      '#image' => $image_url,
      '#additional_class' => $css_class,
      // Context for template suggestions - focus on matched section.
      '#matched_section' => $matched_section,
      '#section_suggestion' => $section_suggestion,
      '#css_class' => $css_class_suggestion,
      '#attached' => [
        'library' => [
          'section_banner/section_banner',
        ],
      ],
      '#cache' => [
        'tags' => $cache_tags,
        'contexts' => [
          'route',
          'url.path',
          'languages:language_interface',
          'languages:language_content',
        ],
        'max-age' => Cache::PERMANENT,
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return [
      'route',
      'url.path',
      'languages:language_interface',
      'languages:language_content',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    // State doesn't have cache tags, so we use a custom cache tag.
    $tags = ['section_banner:banners'];
    
    // Add cache tags for all banner images.
    $banners = $this->state->get('section_banner.banners', []);
    foreach ($banners as $banner) {
      if (!empty($banner['image'])) {
        $file = File::load($banner['image']);
        if ($file) {
          $tags = Cache::mergeTags($tags, $file->getCacheTags());
        }
      }
    }
    
    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

}

