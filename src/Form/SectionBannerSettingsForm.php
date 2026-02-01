<?php

namespace Drupal\section_banner\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\Core\Cache\Cache;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Configure Section Banner settings with multi-language support.
 *
 * @ingroup forms
 */
class SectionBannerSettingsForm extends FormBase {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  public function __construct(StateInterface $state, LanguageManagerInterface $language_manager, RequestStack $request_stack) {
    $this->state = $state;
    $this->languageManager = $language_manager;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state'),
      $container->get('language_manager'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'section_banner_settings_form';
  }

  /**
   * Get all active languages from Drupal.
   *
   * @return array
   *   Array of active language objects keyed by language code.
   */
  protected function getActiveLanguages() {
    return $this->languageManager->getLanguages();
  }

  /**
   * Get the selected language for editing.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string
   *   The language code.
   */
  protected function getSelectedLanguage(FormStateInterface $form_state) {
    $languages = $this->getActiveLanguages();
    $request = $this->requestStack->getCurrentRequest();
    $lang_param = $request ? $request->query->get('lang') : NULL;
    
    // Priority: URL parameter > Form state > Current language > Default (first active language).
    if ($lang_param && isset($languages[$lang_param])) {
      return $lang_param;
    }
    
    $selected = $form_state->get('selected_language');
    if ($selected && isset($languages[$selected])) {
      return $selected;
    }
    
    $current = $this->languageManager->getCurrentLanguage()->getId();
    if (isset($languages[$current])) {
      return $current;
    }
    
    // Fallback to first available language.
    $langcodes = array_keys($languages);
    return reset($langcodes);
  }

  /**
   * Get translation data for a banner.
   *
   * @param array $banner
   *   The banner data.
   * @param string $langcode
   *   The language code.
   *
   * @return array
   *   Translation data with title, body value, and format.
   */
  protected function getBannerTranslation(array $banner, $langcode) {
    $translations = $banner['translations'] ?? [];
    $translation = $translations[$langcode] ?? [];
    
    return [
      'title' => $translation['title'] ?? '',
      'body_value' => is_array($translation['body'] ?? []) ? ($translation['body']['value'] ?? '') : '',
      'body_format' => is_array($translation['body'] ?? []) ? ($translation['body']['format'] ?? 'basic_html') : 'basic_html',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $banners = $this->state->get('section_banner.banners', []);
    
    // Get all active languages from Drupal.
    $languages = $this->getActiveLanguages();
    $selected_language = $this->getSelectedLanguage($form_state);
    $form_state->set('selected_language', $selected_language);

    // Add CSS for better form styling.
    $form['#attached']['library'][] = 'section_banner/admin_form';
    $form['#tree'] = TRUE;

    // Language selector with simple URL redirect on change.
    $current_url = Url::fromRoute('section_banner.settings');
    $language_urls = [];
    
    foreach ($languages as $langcode => $language) {
      $url = $current_url->setOption('query', ['lang' => $langcode])->toString();
      $language_urls[$langcode] = $url;
    }
    
    $form['language_selector'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => [],
      '#default_value' => $selected_language,
      '#weight' => -20,
      '#attributes' => ['class' => ['section-banner-language-selector']],
    ];

    foreach ($languages as $langcode => $language) {
      $form['language_selector']['#options'][$langcode] = $language->getName();
    }
    
    // Simple JavaScript for language switching via URL redirect.
    $form['#attached']['library'][] = 'section_banner/language_switcher';
    $form['#attached']['drupalSettings']['sectionBanner']['languageUrls'] = $language_urls;

    // Language indicator.
    $current_url_string = $current_url->setOption('query', ['lang' => $selected_language])->toString();
    $form['language_indicator'] = [
      '#type' => 'markup',
      '#markup' => '<div class="section-banner-language-indicator"><strong>' . 
        $this->t('Editing Language: @language', ['@language' => $languages[$selected_language]->getName()]) . 
        '</strong> | <small>' . 
        $this->t('Active Languages: @langs', ['@langs' => implode(', ', array_map(function($lang) { return $lang->getName(); }, $languages))]) . 
        '</small></div>',
      '#weight' => -19,
    ];
    
    // Introduction section.
    $form['intro'] = [
      '#type' => 'markup',
      '#markup' => '<div class="section-banner-intro"><p>' . 
        $this->t('Configure section banners with multi-language support. Each banner can have translations for all active languages.') . 
        '</p></div>',
      '#weight' => -10,
    ];

    $form['banners'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Banners Configuration'),
      '#prefix' => '<div id="banners-wrapper" class="section-banner-wrapper">',
      '#suffix' => '</div>',
      '#attributes' => ['class' => ['section-banner-fieldset']],
    ];

    // Get the number of banners from form state or use existing count.
    $banner_count = $form_state->get('banner_count');
    if ($banner_count === NULL) {
      $banner_count = count($banners);
      if ($banner_count === 0) {
        $banner_count = 1;
      }
      $form_state->set('banner_count', $banner_count);
    }

    // Build banner fields for each banner.
    for ($i = 0; $i < $banner_count; $i++) {
      $banner = $banners[$i] ?? [];
      $translation = $this->getBannerTranslation($banner, $selected_language);
      
      $form['banners'][$i] = [
        '#type' => 'details',
        '#title' => $this->t('Banner @num', ['@num' => $i + 1]) . 
          (!empty($translation['title']) ? ': ' . $translation['title'] : ''),
        '#open' => $i === 0,
        '#attributes' => ['class' => ['section-banner-item']],
      ];

      // Content section.
      $form['banners'][$i]['content'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Banner Content (@language)', ['@language' => $languages[$selected_language]->getName()]),
        '#attributes' => ['class' => ['section-banner-content']],
      ];

      // Token examples and help section.
      $token_examples = [
        '[node:title]' => $this->t('Current node title'),
        '[node:url]' => $this->t('Current node URL'),
        '[node:author:name]' => $this->t('Node author name'),
        '[current-user:name]' => $this->t('Current user name'),
        '[current-user:mail]' => $this->t('Current user email'),
        '[site:name]' => $this->t('Site name'),
        '[site:slogan]' => $this->t('Site slogan'),
        '[date:custom:Y-m-d]' => $this->t('Current date (Y-m-d format)'),
        '[date:custom:F j, Y]' => $this->t('Current date (Month Day, Year)'),
      ];
      
      // Build token examples list.
      $token_list_items = [];
      foreach ($token_examples as $token => $description) {
        $token_list_items[] = [
          '#type' => 'markup',
          '#markup' => '<li><code>' . htmlspecialchars($token) . '</code> - ' . $description . '</li>',
        ];
      }
      
      // Token help as a collapsible details element.
      $form['banners'][$i]['content']['token_help'] = [
        '#type' => 'details',
        '#title' => $this->t('📋 Token Examples & Help'),
        '#open' => FALSE,
        '#attributes' => ['class' => ['section-banner-token-help-wrapper']],
        '#weight' => -1,
      ];
      
      $form['banners'][$i]['content']['token_help']['description'] = [
        '#type' => 'markup',
        '#markup' => '<p>' . $this->t('You can use tokens in the Title and Content Text fields to display dynamic content. Tokens are automatically replaced when the banner is displayed.') . '</p>',
      ];
      
      $form['banners'][$i]['content']['token_help']['examples'] = [
        '#type' => 'markup',
        '#markup' => '<div class="section-banner-token-help"><strong>' . $this->t('Common Token Examples:') . '</strong><ul>' . 
          implode('', array_map(function($token, $desc) {
            return '<li><code>' . htmlspecialchars($token) . '</code> - ' . $desc . '</li>';
          }, array_keys($token_examples), $token_examples)) . 
          '</ul></div>',
      ];
      
      // Add link to token browser if Token module is available.
      if (\Drupal::moduleHandler()->moduleExists('token')) {
        try {
          $token_browser_url = Url::fromRoute('token.tree', [], ['attributes' => ['target' => '_blank', 'class' => ['token-browser-link']]]);
          $form['banners'][$i]['content']['token_help']['browser_link'] = [
            '#type' => 'markup',
            '#markup' => '<p class="token-browser-link-wrapper"><a href="' . $token_browser_url->toString() . '" target="_blank" class="button button--small">' . 
              $this->t('🔍 Browse All Available Tokens') . '</a></p>',
          ];
        }
        catch (\Exception $e) {
          // Route might not exist, skip the link.
        }
      }

      $form['banners'][$i]['content']['title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Title'),
        '#default_value' => $translation['title'],
        '#description' => $this->t('The banner title for @language language. Use tokens like [node:title] or [current-user:name] to display dynamic content. See "Token Examples & Help" above for more options.', ['@language' => $languages[$selected_language]->getName()]),
        '#required' => FALSE,
      ];

      $form['banners'][$i]['content']['body'] = [
        '#type' => 'text_format',
        '#title' => $this->t('Content Text'),
        '#default_value' => $translation['body_value'],
        '#format' => $translation['body_format'],
        '#description' => $this->t('The main content text for @language language. Use tokens like [node:title] or [current-user:name] to display dynamic content. See "Token Examples & Help" above for more options.', ['@language' => $languages[$selected_language]->getName()]),
        '#required' => FALSE,
      ];
      
      // Store language code for submission.
      $form['banners'][$i]['content']['_language'] = [
        '#type' => 'hidden',
        '#value' => $selected_language,
      ];

      $form['banners'][$i]['content']['image'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('Banner Image'),
        '#upload_location' => 'public://section-banners/',
        '#default_value' => isset($banner['image']) ? [$banner['image']] : [],
        '#upload_validators' => [
          'file_validate_extensions' => ['png gif jpg jpeg'],
          'file_validate_size' => [25600000],
        ],
        '#description' => $this->t('Upload an image for the banner (shared across all languages).'),
        '#required' => FALSE,
      ];

      // Targeting section.
      $form['banners'][$i]['targeting'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Display Rules'),
        '#attributes' => ['class' => ['section-banner-targeting']],
      ];


      $form['banners'][$i]['targeting']['target_sections'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Target Sections'),
  
        '#default_value' => isset($banner['target_sections']) ? implode("\n", $banner['target_sections']) : '',
        '#required' => FALSE,
        '#rows' => 8,
        '#placeholder' => "/about\nbundle:article\narticles\n/admin/config/content/section-banner",
      ];
      $target_examples = [
        '<strong>' . $this->t('Specific Paths:') . '</strong>',
        '<code>/about</code>, <code>/contact</code>, <code>/admin/config/content/section-banner</code>',
        '<strong>' . $this->t('Wildcard Paths:') . '</strong>',
        '<code>/news/*</code>, <code>/node/*</code>',
        '<strong>' . $this->t('By Content Type:') . '</strong>',
        '<code>bundle:article</code>, <code>bundle:page</code>, <code>node.type.article</code>',
        '<strong>' . $this->t('By View (Machine Name):') . '</strong>',
        '<code>articles</code>, <code>view.articles</code>, <code>news</code>',
        '<strong>' . $this->t('By View Path:') . '</strong>',
        '<code>/articles</code>, <code>/news</code>',
        '<strong>' . $this->t('Route Name:') . '</strong>',
        '<code>section_banner.settings</code>',
      ];

      // Styling section.
      $form['banners'][$i]['styling'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Advance Settings'),
        '#description' => $this->t('Enter where this banner should appear (shared across all languages). One target per line. You can use paths, content types, views, or route names.') . 
        '<div class="section-banner-examples"><h4>' . $this->t('Examples of Target Routes:') . '</h4><ul><li>' . 
        implode('</li><li>', $target_examples) . '</li></ul></div>',
        '#attributes' => ['class' => ['section-banner-styling']],
      ];

      $form['banners'][$i]['styling']['css_class'] = [
        '#type' => 'textfield',
        '#title' => $this->t('CSS Classes'),
        '#default_value' => $banner['css_class'] ?? '',
        '#description' => $this->t('Add custom CSS classes (shared across all languages).'),
        '#required' => FALSE,
      ];
    }

    // Actions section.
    $form['banners']['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['section-banner-actions']],
    ];

    $form['banners']['actions']['add_banner'] = [
      '#type' => 'submit',
      '#value' => $this->t('➕ Add Another Banner'),
      '#submit' => ['::addBanner'],
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'wrapper' => 'banners-wrapper',
        'effect' => 'fade',
      ],
      '#attributes' => ['class' => ['button--primary', 'section-banner-add']],
    ];

    if ($banner_count > 1) {
      $form['banners']['actions']['remove_banner'] = [
        '#type' => 'submit',
        '#value' => $this->t('➖ Remove Last Banner'),
        '#submit' => ['::removeBanner'],
        '#ajax' => [
          'callback' => '::ajaxCallback',
          'wrapper' => 'banners-wrapper',
          'effect' => 'fade',
        ],
        '#attributes' => ['class' => ['button', 'section-banner-remove']],
      ];
    }

    // Add main form actions with save button.
    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => 100,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Configuration'),
      '#button_type' => 'primary',
      '#attributes' => ['class' => ['button--primary']],
    ];

    // Add cache metadata - admin forms should not be cached.
    $form['#cache'] = [
      'max-age' => 0,
      'contexts' => [
        'user.permissions',
        'url.query_args:lang',
        'languages:language_interface',
      ],
      'tags' => ['section_banner:banners'],
    ];

    return $form;
  }


  /**
   * Ajax callback for adding/removing banners.
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state) {
    return $form['banners'];
  }

  /**
   * Submit handler for adding a banner.
   */
  public function addBanner(array &$form, FormStateInterface $form_state) {
    $banner_count = $form_state->get('banner_count');
    $form_state->set('banner_count', $banner_count + 1);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for removing a banner.
   */
  public function removeBanner(array &$form, FormStateInterface $form_state) {
    $banner_count = $form_state->get('banner_count');
    if ($banner_count > 1) {
      $form_state->set('banner_count', $banner_count - 1);
    }
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    
    // Get selected language and all active languages.
    $selected_language = $this->getSelectedLanguage($form_state);
    $languages = $this->getActiveLanguages();
    
    // Load existing banners to preserve all translations.
    $existing_banners = $this->state->get('section_banner.banners', []);
    $banners = [];

    if (isset($values['banners'])) {
      foreach ($values['banners'] as $key => $banner_data) {
        // Skip non-numeric keys (like 'actions').
        if ($key === 'actions' || !is_numeric($key)) {
          continue;
        }

        $key = (int) $key;
        
        // Get form values.
        $title = trim($banner_data['content']['title'] ?? '');
        $body_value = $banner_data['content']['body']['value'] ?? '';
        $body_format = $banner_data['content']['body']['format'] ?? 'basic_html';
        $image = $banner_data['content']['image'] ?? [];
        $target_sections_input = trim($banner_data['targeting']['target_sections'] ?? '');
        $css_class = trim($banner_data['styling']['css_class'] ?? '');
        
        // Get language from hidden field - CRITICAL for preserving translations.
        $language = $banner_data['content']['_language'] ?? $selected_language;
        $language = strtolower(trim($language));
        
        // Validate language code - must be an active language.
        if (!isset($languages[$language])) {
          $language = $selected_language;
        }

        // Get existing banner or create new one - IMPORTANT: preserve ALL existing data.
        $existing_banner_data = $existing_banners[$key] ?? [];
        
        // Start with existing banner data to preserve everything.
        $banner = $existing_banner_data;
        
        // Deep copy existing translations to ensure we don't lose any language data.
        $translations = [];
        if (isset($existing_banner_data['translations']) && is_array($existing_banner_data['translations'])) {
          foreach ($existing_banner_data['translations'] as $langcode => $translation) {
            // Preserve all existing translations for all languages.
            $translations[$langcode] = $translation;
          }
        }
        
        // Update translation for the selected language ONLY - don't overwrite others.
        $translations[$language] = [
          'title' => $title,
          'body' => [
            'value' => $body_value,
            'format' => $body_format,
          ],
        ];
        
        // Update banner with ALL translations (preserving all languages).
        $banner['translations'] = $translations;
        
        // Update shared fields (not language-specific) - only if provided.
        if (!empty($css_class)) {
          $banner['css_class'] = $css_class;
        } elseif (isset($existing_banner_data['css_class'])) {
          // Preserve existing CSS class if not being updated.
          $banner['css_class'] = $existing_banner_data['css_class'];
        }

        // Handle image file - update if new one uploaded, preserve if not.
        if (!empty($image[0])) {
          $file_id = $image[0];
          $file = File::load($file_id);
          if ($file) {
            $file->setPermanent();
            $file->save();
            $banner['image'] = $file_id;
          }
        } elseif (isset($existing_banner_data['image'])) {
          // Preserve existing image if not being updated.
          $banner['image'] = $existing_banner_data['image'];
        }

        // Process target sections - update if provided, preserve if not.
        if (!empty($target_sections_input)) {
          $target_sections = [];
          $lines = explode("\n", $target_sections_input);
          foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
              if (strpos($line, ',') !== FALSE) {
                $items = explode(',', $line);
                foreach ($items as $item) {
                  $item = trim($item);
                  if (!empty($item)) {
                    $target_sections[] = $item;
                  }
                }
              } else {
                $target_sections[] = $line;
              }
            }
          }
          $banner['target_sections'] = $target_sections;
        } elseif (isset($existing_banner_data['target_sections'])) {
          // Preserve existing target sections if not being updated.
          $banner['target_sections'] = $existing_banner_data['target_sections'];
        }

        // Save banner if it has content or translations.
        if (!empty($title) || !empty($body_value) || !empty($banner['image']) || 
            !empty($banner['target_sections']) || !empty($banner['translations'])) {
          $banners[$key] = $banner;
        }
      }
    }
    
    // IMPORTANT: Preserve ALL existing banners that weren't in the form submission.
    // This ensures banners with translations in other languages are not lost.
    foreach ($existing_banners as $key => $existing_banner) {
      if (!isset($banners[$key])) {
        // Preserve banner if it has any content or translations.
        if (!empty($existing_banner['translations']) || 
            !empty($existing_banner['image']) || 
            !empty($existing_banner['target_sections'])) {
          $banners[$key] = $existing_banner;
        }
      }
    }
    
    // Sort by key to maintain order, then re-index to remove gaps.
    ksort($banners);
    $banners = array_values($banners);
    
    // Save banners to state.
    $this->state->set('section_banner.banners', $banners);
    
    // Invalidate cache for section banner block.
    \Drupal::service('cache_tags.invalidator')->invalidateTags(['section_banner:banners']);

    // Show success message with saved languages.
    $saved_languages = [];
    foreach ($banners as $banner) {
      if (isset($banner['translations'])) {
        $saved_languages = array_merge($saved_languages, array_keys($banner['translations']));
      }
    }
    $saved_languages = array_unique($saved_languages);
    
    $this->messenger()->addStatus($this->t('Section banner configuration saved for @language. All translations preserved: @langs', [
      '@language' => $languages[$selected_language]->getName(),
      '@langs' => implode(', ', array_map(function($langcode) use ($languages) {
        return isset($languages[$langcode]) ? $languages[$langcode]->getName() : $langcode;
      }, $saved_languages)),
    ]));
    
    // Redirect back to the same language URL.
    $form_state->setRedirect('section_banner.settings', [], [
      'query' => ['lang' => $selected_language],
    ]);
  }

}
