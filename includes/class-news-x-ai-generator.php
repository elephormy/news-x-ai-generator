<?php
/**
 * Core generator class for News X AI Generator
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once(plugin_dir_path(__FILE__) . 'class-news-x-ai-news-fetcher.php');

class NewsX_AI_Generator_Core {
    
    private $api_key;
    private $image_generator;
    
    public function __construct() {
        $this->api_key = get_option('news_x_ai_gemini_api_key');
        $this->image_generator = new NewsX_AI_Image_Generator();
    }
    
    /**
     * Generate posts using Gemini AI
     */
    public function generate_posts($count = 3, $categories = array(), $post_status = 'publish') {
        error_log('News X AI Generator: generate_posts called with count=' . $count . ', categories=' . implode(',', $categories));
        
        if (empty($this->api_key)) {
            error_log('News X AI Generator: API key is empty');
            return array(
                'success' => false,
                'message' => __('API key not configured', 'news-x-ai-generator')
            );
        }
        
        error_log('News X AI Generator: API key length: ' . strlen($this->api_key));
        
        $generated_posts = array();
        $errors = array();
        
        for ($i = 0; $i < $count; $i++) {
            error_log('News X AI Generator: Generating post ' . ($i + 1) . ' of ' . $count);
            try {
                $result = $this->generate_single_post($categories, $post_status);
                
                if ($result['success']) {
                    error_log('News X AI Generator: Post ' . ($i + 1) . ' generated successfully');
                    $generated_posts[] = $result['post'];
                } else {
                    error_log('News X AI Generator: Post ' . ($i + 1) . ' failed: ' . $result['message']);
                    $errors[] = $result['message'] ?: 'Unknown error occurred during post generation';
                }
            } catch (Exception $e) {
                error_log('News X AI Generator: Exception during post ' . ($i + 1) . ': ' . $e->getMessage());
                $errors[] = 'Exception during post generation: ' . $e->getMessage();
            }
        }
        
        error_log('News X AI Generator: Generation complete. Success: ' . count($generated_posts) . ', Errors: ' . count($errors));
        
        // Update statistics
        $total_generated = get_option('news_x_ai_total_posts_generated', 0) + count($generated_posts);
        update_option('news_x_ai_total_posts_generated', $total_generated);
        update_option('news_x_ai_last_generation', current_time('mysql'));
        
        return array(
            'success' => count($generated_posts) > 0,
            'posts' => $generated_posts,
            'errors' => $errors,
            'total_generated' => count($generated_posts)
        );
    }
    
    /**
     * Generate a single post
     */
    private function generate_single_post($categories = array(), $post_status = 'publish') {
        // Get news topics
        $topics = $this->get_news_topics();
        $topic = $topics[array_rand($topics)];
        
        // Generate content using Gemini AI
        $content_data = $this->generate_content_with_gemini($topic);
        
        if (!$content_data['success']) {
            return $content_data;
        }
        
        // Generate or fetch image
        try {
            error_log('News X AI Generator: Generating image for post with title: ' . $content_data['title'] . ', topic: ' . $topic);
            $image_data = $this->generate_image($content_data['title'], $topic);
            error_log('News X AI Generator: Image generation result - success: ' . ($image_data['success'] ? 'true' : 'false') . ', source: ' . ($image_data['source'] ?? 'unknown'));
        } catch (Exception $e) {
            // Log image generation error but continue with post creation
            error_log('News X AI Generator - Image generation error: ' . $e->getMessage());
            $image_data = array('success' => false, 'image_url' => '');
        }
        
        // Determine and create category automatically
        $category_id = $this->determine_and_create_category($content_data['title'], $content_data['content'], $topic);
        
        // Create post with automatic category and specified status
        $post_data = array(
            'post_title' => $content_data['title'],
            'post_content' => $content_data['content'],
            'post_excerpt' => $content_data['excerpt'],
            'post_status' => $post_status, // Use the specified post status (publish or draft)
            'post_author' => get_current_user_id(),
            'post_type' => 'post',
            'post_category' => array($category_id) // Use the determined category
        );
        
        // Insert post
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            return array(
                'success' => false,
                'message' => $post_id->get_error_message()
            );
        }
        
        // Set featured image if available
        if ($image_data['success'] && get_option('news_x_ai_featured_image', true)) {
            try {
                $this->set_featured_image($post_id, $image_data['image_url']);
            } catch (Exception $e) {
                // Log featured image error but continue
                error_log('News X AI Generator - Featured image error: ' . $e->getMessage());
            }
        }
        
        // Add SEO meta if enabled
        if (get_option('news_x_ai_seo_optimization', true)) {
            try {
                $this->add_seo_meta($post_id, $content_data);
            } catch (Exception $e) {
                // Log SEO meta error but continue
                error_log('News X AI Generator - SEO meta error: ' . $e->getMessage());
            }
        }
        
        // Log the generation
        try {
            $this->log_generation($post_id, $content_data['title'], 'success');
        } catch (Exception $e) {
            // Log logging error but continue
            error_log('News X AI Generator - Logging error: ' . $e->getMessage());
        }
        
        return array(
            'success' => true,
            'post' => array(
                'id' => $post_id,
                'title' => $content_data['title'],
                'url' => get_permalink($post_id),
                'edit_url' => get_edit_post_link($post_id),
                'category_id' => $category_id,
                'category_name' => get_cat_name($category_id),
                'status' => ucfirst($post_status)
            )
        );
    }
    
    /**
     * List available Gemini models
     */
    public function list_available_models() {
        error_log('News X AI Generator: list_available_models called');
        
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => 'API key not configured'
            );
        }
        
        $response = wp_remote_get('https://generativelanguage.googleapis.com/v1/models', array(
            'headers' => array(
                'x-goog-api-key' => $this->api_key
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('News X AI Generator: wp_remote_get error in list_available_models: ' . $response->get_error_message());
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        error_log('News X AI Generator: List models response status: ' . wp_remote_retrieve_response_code($response));
        error_log('News X AI Generator: List models response body: ' . $body);
        
        if (empty($data) || isset($data['error'])) {
            $error_message = 'Unknown API error';
            if (isset($data['error']['message'])) {
                $error_message = $data['error']['message'];
            }
            error_log('News X AI Generator: List models API error: ' . $error_message);
            return array(
                'success' => false,
                'message' => $error_message
            );
        }
        
        return array(
            'success' => true,
            'models' => $data
        );
    }

    /**
     * Generate content using Gemini AI
     */
    private function generate_content_with_gemini($topic) {
        error_log('News X AI Generator: generate_content_with_gemini called with topic: ' . $topic);
        
        $prompt = $this->build_gemini_prompt($topic);
        error_log('News X AI Generator: Prompt length: ' . strlen($prompt));
        
        // Try gemini-2.5-flash first (free tier), then gemini-2.5-pro, then fallback to gemini-1.5-flash
        $models = array('gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-1.5-flash');
        $last_error = '';
        
        foreach ($models as $model) {
            error_log('News X AI Generator: Trying model: ' . $model);
            
            $response = wp_remote_post("https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent", array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $this->api_key
                ),
                'body' => json_encode(array(
                    'contents' => array(
                        array(
                            'parts' => array(
                                array('text' => $prompt)
                            )
                        )
                    ),
                    'generationConfig' => array(
                        'temperature' => 0.7,
                        'topK' => 40,
                        'topP' => 0.95,
                        'maxOutputTokens' => $this->get_content_length_tokens()
                    )
                )),
                'timeout' => 60
            ));
            
            if (is_wp_error($response)) {
                $last_error = $response->get_error_message();
                error_log('News X AI Generator: wp_remote_post error with model ' . $model . ': ' . $last_error);
                continue;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            error_log('News X AI Generator: API response status for model ' . $model . ': ' . wp_remote_retrieve_response_code($response));
            error_log('News X AI Generator: API response body length for model ' . $model . ': ' . strlen($body));
            error_log('News X AI Generator: API response body for model ' . $model . ': ' . $body);
            
            if (empty($data) || isset($data['error'])) {
                $error_message = 'Unknown API error';
                if (isset($data['error']['message'])) {
                    $error_message = $data['error']['message'];
                }
                $last_error = $error_message;
                error_log('News X AI Generator: API error with model ' . $model . ': ' . $error_message);
                continue;
            }
            
            if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                $last_error = 'Invalid response format from Gemini API';
                error_log('News X AI Generator: Invalid response format from Gemini API with model ' . $model);
                continue;
            }
            
            $content = $data['candidates'][0]['content']['parts'][0]['text'];
            error_log('News X AI Generator: Successfully received content from model ' . $model . ', length: ' . strlen($content));
            
            // Parse the response
            return $this->parse_gemini_response($content);
        }
        
        // If we get here, all models failed
        error_log('News X AI Generator: All models failed. Last error: ' . $last_error);
        return array(
            'success' => false,
            'message' => $last_error
        );
    }
    
    /**
     * Build Gemini prompt
     */
    private function build_gemini_prompt($topic) {
        $content_length = get_option('news_x_ai_content_length', 'medium');
        $current_date = date('F j, Y');
        
        $prompt = "You are a professional journalist assistant and content creator. Your task is to generate a **professional, SEO-optimized, and AdSense-compliant article** based on **real news from today's date: {$current_date}**.\n\n";
        
        $prompt .= "🔍 Step 1: Search the web to find **a trending or breaking news topic from today** in one of the following categories (pick the most relevant or impactful news of the day):\n\n";
        
        $prompt .= "### Supported Categories:\n";
        $prompt .= "- 🌍 World News (conflict-free, politics-free headlines if AdSense)\n";
        $prompt .= "- 🏛️ Economy & Finance (stocks, inflation, fintech, crypto)\n";
        $prompt .= "- 💻 Technology (AI, software, hardware, cybersecurity)\n";
        $prompt .= "- 📱 Social Media & Digital Trends\n";
        $prompt .= "- 🏥 Health & Wellness (avoid medical advice)\n";
        $prompt .= "- 🍎 Food & Nutrition Trends\n";
        $prompt .= "- 🌱 Environment & Climate\n";
        $prompt .= "- 🧪 Science & Innovation\n";
        $prompt .= "- 🚀 Space & Astronomy\n";
        $prompt .= "- 🎬 Entertainment & Celebrities (non-scandalous)\n";
        $prompt .= "- 🎮 Gaming & eSports\n";
        $prompt .= "- 🧘 Lifestyle & Self-improvement\n";
        $prompt .= "- 🏡 Home & Design\n";
        $prompt .= "- 🛒 E-commerce & Product Launches\n";
        $prompt .= "- 📈 Business & Startups\n";
        $prompt .= "- 📚 Education & E-learning\n";
        $prompt .= "- 📦 Logistics & Supply Chains\n";
        $prompt .= "- 🚗 Automotive & Electric Vehicles\n";
        $prompt .= "- 🛫 Travel & Destinations\n";
        $prompt .= "- 📱 Mobile & Apps\n";
        $prompt .= "- 👨‍💻 Freelancing & Remote Work\n";
        $prompt .= "- ⚽ Sports (non-violent, AdSense-compliant topics)\n\n";
        
        // If we have real news data, use it
        if (is_array($topic) && isset($topic['title'])) {
            $prompt .= "🎯 Step 2: Use the following real news data as your primary source:\n\n";
            $prompt .= "**Source Article:** " . $topic['title'] . "\n";
            $prompt .= "**Source:** " . $topic['source'] . "\n";
            $prompt .= "**Category:** " . $topic['category'] . "\n";
            
            if (!empty($topic['description'])) {
                $prompt .= "**Description:** " . $topic['description'] . "\n\n";
            }
            
            if (!empty($topic['facts'])) {
                $prompt .= "**Verified Facts:**\n";
                
                if (!empty($topic['facts']['dates'])) {
                    $prompt .= "• Dates: " . implode(", ", $topic['facts']['dates']) . "\n";
                }
                
                if (!empty($topic['facts']['statistics'])) {
                    $prompt .= "• Statistics: " . implode(", ", $topic['facts']['statistics']) . "\n";
                }
                
                if (!empty($topic['facts']['quotes'])) {
                    $prompt .= "• Quotes:\n";
                    foreach ($topic['facts']['quotes'] as $quote) {
                        $prompt .= "  - \"" . $quote . "\"\n";
                    }
                }
                
                if (!empty($topic['facts']['organizations'])) {
                    $prompt .= "• Organizations: " . implode(", ", $topic['facts']['organizations']) . "\n";
                }
                
                $prompt .= "\n";
            }
        } else {
            // Use the provided topic as a starting point
            $topic_string = is_string($topic) ? $topic : 'General News';
            $prompt .= "🎯 Step 2: Research and write about: **{$topic_string}**\n\n";
        }
        
        $prompt .= "## 📝 ARTICLE STRUCTURE:\n";
        $prompt .= "1. **Title** – Engaging, SEO-optimized, not clickbait\n";
        $prompt .= "2. **Meta Description** – Max 160 characters\n";
        $prompt .= "3. **5 SEO Keywords** – Related to the article\n";
        $prompt .= "4. **URL Slug** – Suggested article URL\n";
        $prompt .= "5. **Length** – Between " . $this->get_word_count($content_length) . " words\n";
        $prompt .= "6. **Tone** – Clear, professional, and neutral\n";
        $prompt .= "7. **Structure**:\n";
        $prompt .= "   - Introduction (context and hook)\n";
        $prompt .= "   - Several subheadings (H2 and H3 as needed)\n";
        $prompt .= "   - Bullet points or lists if needed\n";
        $prompt .= "   - Data or insights if applicable\n";
        $prompt .= "   - Conclusion with wrap-up or CTA\n";
        $prompt .= "8. **Sources** – List sources used (with URLs if possible)\n\n";
        
        $prompt .= "## ⚠️ Must obey the following rules:\n";
        $prompt .= "- ✅ Must be based on real **news from today's date: {$current_date}**\n";
        $prompt .= "- ✅ Must be original and AI-generated\n";
        $prompt .= "- ✅ Must respect **Google AdSense** content policies:\n";
        $prompt .= "  - No violence, hate, adult content, medical advice, illegal content, drugs, or politics-heavy opinions\n";
        $prompt .= "- ✅ Avoid biased or controversial stances\n";
        $prompt .= "- ✅ Use proper HTML formatting (<h2>, <h3>, <p>, <ul>, <li>)\n";
        $prompt .= "- ✅ Include double line breaks between paragraphs\n";
        $prompt .= "- ✅ **CRITICAL: Use [MINI_TITLE]...[/MINI_TITLE] markers for ALL subheadings**\n";
        $prompt .= "- ✅ **CRITICAL: Examples of mini-title markers:**\n";
        $prompt .= "  - [MINI_TITLE]Key Developments[/MINI_TITLE]\n";
        $prompt .= "  - [MINI_TITLE]Industry Impact[/MINI_TITLE]\n";
        $prompt .= "  - [MINI_TITLE]Future Outlook[/MINI_TITLE]\n";
        $prompt .= "  - [MINI_TITLE]Expert Analysis[/MINI_TITLE]\n";
        $prompt .= "  - [MINI_TITLE]Market Response[/MINI_TITLE]\n";
        $prompt .= "  - [MINI_TITLE]Technical Details[/MINI_TITLE]\n";
        $prompt .= "  - [MINI_TITLE]Consumer Impact[/MINI_TITLE]\n";
        $prompt .= "  - [MINI_TITLE]Global Implications[/MINI_TITLE]\n";
        $prompt .= "- ✅ Write in third person, past tense\n";
        $prompt .= "- ✅ Use active voice and strong verbs\n";
        $prompt .= "- ✅ Keep paragraphs short (2-3 sentences max)\n";
        $prompt .= "- ✅ Use transition words (However, Meanwhile, Furthermore, Additionally, Moreover)\n";
        $prompt .= "- ✅ DO NOT repeat the headline in the article body\n";
        $prompt .= "- ✅ DO NOT include bylines or author names in content\n";
        $prompt .= "- ✅ DO NOT invent quotes, statistics, or fictional characters\n";
        $prompt .= "- ✅ Ensure all dates and references are current to 2025\n\n";
        
        $prompt .= "Now, please begin by:\n";
        $prompt .= "1. Searching for relevant real-time information online from {$current_date}\n";
        $prompt .= "2. Writing the full article using the structure above\n\n";
        
        $prompt .= "Format your response exactly as:\n";
        $prompt .= "TITLE: [Your headline]\n";
        $prompt .= "META_DESCRIPTION: [Your meta description - max 160 characters]\n";
        $prompt .= "SEO_KEYWORDS: [5 comma-separated keywords]\n";
        $prompt .= "URL_SLUG: [suggested-url-slug]\n";
        $prompt .= "CONTENT: [Your formatted article content with proper HTML tags]\n";
        $prompt .= "SOURCES: [List of sources used]\n";
        
        return $prompt;
    }
    
    /**
     * Parse Gemini response
     */
    private function parse_gemini_response($content) {
        $lines = explode("\n", $content);
        $parsed = array(
            'title' => '',
            'meta_description' => '',
            'seo_keywords' => '',
            'url_slug' => '',
            'content' => '',
            'sources' => ''
        );
        
        $current_section = '';
        $content_lines = array();
        $sources_lines = array();
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line)) continue;
            
            if (strpos($line, 'TITLE:') === 0) {
                $parsed['title'] = trim(substr($line, 6));
            } elseif (strpos($line, 'META_DESCRIPTION:') === 0) {
                $parsed['meta_description'] = trim(substr($line, 17));
            } elseif (strpos($line, 'SEO_KEYWORDS:') === 0) {
                $parsed['seo_keywords'] = trim(substr($line, 13));
            } elseif (strpos($line, 'URL_SLUG:') === 0) {
                $parsed['url_slug'] = trim(substr($line, 9));
            } elseif (strpos($line, 'CONTENT:') === 0) {
                $current_section = 'content';
            } elseif (strpos($line, 'SOURCES:') === 0) {
                $current_section = 'sources';
            } elseif ($current_section === 'content') {
                $content_lines[] = $line;
            } elseif ($current_section === 'sources') {
                $sources_lines[] = $line;
            }
        }
        
        $parsed['content'] = implode("\n", $content_lines);
        $parsed['sources'] = implode("\n", $sources_lines);
        
        // Post-process content for professional newspaper structure
        $parsed['content'] = $this->format_professional_content($parsed['content'], $parsed['title']);
        
        // Validate parsed content
        if (empty($parsed['title']) || empty($parsed['content'])) {
            return array(
                'success' => false,
                'message' => 'Failed to parse Gemini response'
            );
        }
        
        // Use meta_description as excerpt if available, otherwise create one
        $excerpt = !empty($parsed['meta_description']) ? $parsed['meta_description'] : '';
        if (empty($excerpt) && !empty($parsed['content'])) {
            // Create excerpt from first paragraph
            $first_paragraph = strip_tags($parsed['content']);
            $excerpt = substr($first_paragraph, 0, 160);
            if (strlen($first_paragraph) > 160) {
                $excerpt .= '...';
            }
        }
        
        // Use seo_keywords as keywords if available
        $keywords = !empty($parsed['seo_keywords']) ? $parsed['seo_keywords'] : '';
        
        return array(
            'success' => true,
            'title' => $parsed['title'],
            'excerpt' => $excerpt,
            'content' => $parsed['content'],
            'keywords' => $keywords,
            'meta_description' => $parsed['meta_description'],
            'url_slug' => $parsed['url_slug'],
            'sources' => $parsed['sources']
        );
    }
    
    /**
     * Format content with professional newspaper structure
     */
    private function format_professional_content($content, $title) {
        // Remove any duplicate title from content
        $title_variations = array(
            $title,
            '<h1>' . $title . '</h1>',
            '<h2>' . $title . '</h2>',
            '# ' . $title,
            '## ' . $title
        );
        
        foreach ($title_variations as $variation) {
            $content = str_ireplace($variation, '', $content);
        }
        
        // Clean up content
        $content = trim($content);
        
        // NEW: Process mini-title markers first
        $content = $this->process_mini_title_markers($content);
        
        // Ensure proper paragraph structure
        $content = $this->improve_paragraph_structure($content);
        
        // Add professional styling classes
        $content = $this->add_professional_styling($content);
        
        // Ensure proper HTML structure
        $content = $this->ensure_proper_html($content);
        
        return $content;
    }
    
    /**
     * NEW: Process mini-title markers and convert them to HTML headings
     */
    private function process_mini_title_markers($content) {
        // Pattern to match [MINI_TITLE]...[/MINI_TITLE] markers
        $pattern = '/\[MINI_TITLE\](.*?)\[\/MINI_TITLE\]/i';
        
        // Replace markers with proper HTML h3 tags
        $content = preg_replace($pattern, '<h3>$1</h3>', $content);
        
        // Also handle any variations or malformed markers
        $variations = array(
            '/\[MINI_TITLE\s*\](.*?)\[\/MINI_TITLE\s*\]/i',
            '/\[MINI_TITLE\](.*?)\[\/MINI_TITLE\s*\]/i',
            '/\[MINI_TITLE\s*\](.*?)\[\/MINI_TITLE\]/i',
            '/\[MINI_TITLE\](.*?)\[\/MINI_TITLE\]/i',
            '/\[MINI_TITLE\](.*?)\[\/MINI_TITLE\]/i'
        );
        
        foreach ($variations as $variation) {
            $content = preg_replace($variation, '<h3>$1</h3>', $content);
        }
        
        // Clean up any extra whitespace around the headings
        $content = preg_replace('/\s*<h3>\s*/', '<h3>', $content);
        $content = preg_replace('/\s*<\/h3>\s*/', '</h3>', $content);
        
        // Ensure proper spacing around headings
        $content = preg_replace('/<\/h3>\s*<h3>/', '</h3><h3>', $content);
        $content = preg_replace('/<\/p>\s*<h3>/', '</p><h3>', $content);
        $content = preg_replace('/<\/h3>\s*<p>/', '</h3><p>', $content);
        
        return $content;
    }
    
    /**
     * Improve paragraph structure for readability
     */
    private function improve_paragraph_structure($content) {
        // First, normalize line breaks
        $content = str_replace(array("\r\n", "\r"), "\n", $content);
        
        // If content is one long paragraph without breaks, split it intelligently
        if (strpos($content, "\n") === false && strlen($content) > 500) {
            // Split by sentences and group into logical paragraphs
            $sentences = preg_split('/(?<=[.!?])\s+/', $content, -1, PREG_SPLIT_NO_EMPTY);
            $paragraphs = array();
            $current_paragraph = '';
            $sentence_count = 0;
            
            foreach ($sentences as $sentence) {
                $current_paragraph .= $sentence . ' ';
                $sentence_count++;
                
                // Create a new paragraph every 3-4 sentences or when we hit certain keywords
                if ($sentence_count >= 3 || 
                    preg_match('/\b(however|meanwhile|furthermore|additionally|moreover|nevertheless|consequently|therefore|thus|hence|as a result|in conclusion|in summary)\b/i', $sentence)) {
                    $paragraphs[] = trim($current_paragraph);
                    $current_paragraph = '';
                    $sentence_count = 0;
                }
            }
            
            // Add any remaining content as the last paragraph
            if (!empty(trim($current_paragraph))) {
                $paragraphs[] = trim($current_paragraph);
            }
        } else {
            // Split content into paragraphs by line breaks
            $paragraphs = preg_split('/\n/', $content);
        }
        
        $formatted_paragraphs = array();
        
        foreach ($paragraphs as $index => $paragraph) {
            $paragraph = trim($paragraph);
            if (empty($paragraph)) continue;
            
            // Check if it's already wrapped in HTML tags
            if (!preg_match('/^<(h[1-6]|p|blockquote|div|ul|ol|li)/', $paragraph)) {
                // Check if it's a list item
                if (preg_match('/^[-*•]\s+/', $paragraph)) {
                    $paragraph = '<li>' . preg_replace('/^[-*•]\s+/', '', $paragraph) . '</li>';
                }
                // NEW: Skip old heading detection since we now use explicit markers
                // The process_mini_title_markers method already handles this
                else {
                    // Regular paragraph - ensure it's properly wrapped
                    $paragraph = '<p>' . $paragraph . '</p>';
                }
            }
            
            $formatted_paragraphs[] = $paragraph;
        }
        
        // Group list items together
        $final_paragraphs = array();
        $current_list = array();
        
        foreach ($formatted_paragraphs as $paragraph) {
            if (strpos($paragraph, '<li>') === 0) {
                $current_list[] = $paragraph;
            } else {
                // If we have a list in progress, close it
                if (!empty($current_list)) {
                    $final_paragraphs[] = '<ul>' . implode('', $current_list) . '</ul>';
                    $current_list = array();
                }
                $final_paragraphs[] = $paragraph;
            }
        }
        
        // Close any remaining list
        if (!empty($current_list)) {
            $final_paragraphs[] = '<ul>' . implode('', $current_list) . '</ul>';
        }
        
        return implode('', $final_paragraphs);
    }
    
    /**
     * Add professional styling classes and structure
     */
    private function add_professional_styling($content) {
        // Add lead paragraph class to first paragraph
        $content = preg_replace(
            '/^<p>/',
            '<p class="lead-paragraph">',
            $content,
            1
        );
        
        // Add proper spacing and classes
        $content = str_replace('<h3>', '<h3 class="section-heading">', $content);
        $content = str_replace('<blockquote>', '<blockquote class="professional-quote">', $content);
        
        // Add styling to lists
        $content = str_replace('<ul>', '<ul class="article-list">', $content);
        $content = str_replace('<ol>', '<ol class="article-list">', $content);
        
        // Add styling to list items
        $content = str_replace('<li>', '<li class="article-list-item">', $content);
        
        return $content;
    }
    
    /**
     * Ensure proper HTML structure
     */
    private function ensure_proper_html($content) {
        // Remove any malformed HTML
        $content = preg_replace('/<\/?h[1-2][^>]*>/', '', $content); // Remove h1 and h2 tags
        
        // Ensure paragraphs are properly closed
        $content = preg_replace('/<p([^>]*)>([^<]*?)(?=<p|$)/s', '<p$1>$2</p>', $content);
        
        // Ensure list items are properly closed
        $content = preg_replace('/<li([^>]*)>([^<]*?)(?=<li|<\/ul>|<\/ol>|$)/s', '<li$1>$2</li>', $content);
        
        // Clean up multiple line breaks and spaces
        $content = preg_replace('/\n{3,}/', "\n", $content);
        $content = preg_replace('/\s{2,}/', ' ', $content);
        
        // Remove any empty paragraphs
        $content = preg_replace('/<p[^>]*>\s*<\/p>/', '', $content);
        
        // Add newspaper-style formatting
        $content = '<div class="newspaper-article">' . "\n" . $content . "\n" . '</div>';
        
        return $content;
    }
    
    /**
     * Generate image for the post
     */
    private function generate_image($title, $topic) {
        return $this->image_generator->generate_image($title, $topic);
    }
    
    /**
     * Set featured image
     */
    private function set_featured_image($post_id, $image_url) {
        error_log('News X AI Generator: Setting featured image for post ' . $post_id . ' with URL: ' . $image_url);
        
        // Check if the image URL is already a local WordPress URL
        if (strpos($image_url, home_url()) === 0) {
            // Image is already in WordPress uploads, find the attachment
            $upload_dir = wp_upload_dir();
            $relative_path = str_replace($upload_dir['baseurl'], '', $image_url);
            $file_path = $upload_dir['basedir'] . $relative_path;
            
            if (file_exists($file_path)) {
                // Find attachment by file path
                global $wpdb;
                $attachment_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE guid = %s AND post_type = 'attachment'",
                    $image_url
                ));
                
                if ($attachment_id) {
                    $result = set_post_thumbnail($post_id, $attachment_id);
                    if ($result) {
                        error_log('News X AI Generator: Featured image set successfully for post ' . $post_id . ' (existing attachment)');
                        return true;
                    }
                }
            }
        }
        
        // Download and create attachment
        $image_generator = new NewsX_AI_Image_Generator();
        $attachment_id = $image_generator->download_image($image_url, $post_id, 'Featured Image');
        
        if ($attachment_id && !is_wp_error($attachment_id)) {
            $result = set_post_thumbnail($post_id, $attachment_id);
            if ($result) {
                error_log('News X AI Generator: Featured image set successfully for post ' . $post_id . ' (new attachment)');
            } else {
                error_log('News X AI Generator: Failed to set featured image for post ' . $post_id);
            }
            return $result;
        } else {
            error_log('News X AI Generator: Failed to download image for post ' . $post_id . '. Error: ' . (is_wp_error($attachment_id) ? $attachment_id->get_error_message() : 'Unknown error'));
            return false;
        }
    }
    
    /**
     * Add SEO meta
     */
    private function add_seo_meta($post_id, $content_data) {
        // Add meta description (prefer AI-generated meta description, fallback to excerpt)
        $meta_description = '';
        if (!empty($content_data['meta_description'])) {
            $meta_description = $content_data['meta_description'];
        } elseif (!empty($content_data['excerpt'])) {
            $meta_description = $content_data['excerpt'];
        }
        
        if (!empty($meta_description)) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', $meta_description);
            // Also add to general meta description
            update_post_meta($post_id, '_meta_description', $meta_description);
        }
        
        // Add focus keyword (prefer AI-generated SEO keywords, fallback to keywords)
        $keywords = '';
        if (!empty($content_data['seo_keywords'])) {
            $keywords = $content_data['seo_keywords'];
        } elseif (!empty($content_data['keywords'])) {
            $keywords = $content_data['keywords'];
        }
        
        if (!empty($keywords)) {
            $keyword_array = explode(',', $keywords);
            $focus_keyword = trim($keyword_array[0]);
            update_post_meta($post_id, '_yoast_wpseo_focuskw', $focus_keyword);
            
            // Add all keywords as meta keywords
            $clean_keywords = array_map('trim', $keyword_array);
            update_post_meta($post_id, '_meta_keywords', implode(', ', $clean_keywords));
        }
        
        // Add URL slug if provided
        if (!empty($content_data['url_slug'])) {
            // Update the post slug
            wp_update_post(array(
                'ID' => $post_id,
                'post_name' => sanitize_title($content_data['url_slug'])
            ));
        }
        
        // Add sources as custom field
        if (!empty($content_data['sources'])) {
            update_post_meta($post_id, '_article_sources', $content_data['sources']);
        }
    }
    
    /**
     * Log generation
     */
    private function log_generation($post_id, $title, $status) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'news_x_ai_logs';
        
        $wpdb->insert(
            $table_name,
            array(
                'post_id' => $post_id,
                'title' => $title,
                'status' => $status
            ),
            array('%d', '%s', '%s')
        );
    }
    
    /**
     * Get news topics
     */
    private function get_news_topics() {
        // Try to fetch real news first
        try {
            $news_fetcher = new NewsX_AI_News_Fetcher();
            $news_items = $news_fetcher->get_current_news('general');
            
            if (!empty($news_items) && is_array($news_items)) {
                error_log('News X AI Generator: Successfully fetched ' . count($news_items) . ' real news items');
                return array_map(function($item) {
                    return array(
                        'title' => $item['title'],
                        'description' => $item['description'],
                        'source' => $item['source'],
                        'url' => $item['url'],
                        'facts' => $item['facts'] ?? array(),
                        'category' => $item['category']
                    );
                }, $news_items);
            }
        } catch (Exception $e) {
            error_log('News X AI Generator: News fetcher failed: ' . $e->getMessage());
        }
        
        // Use enhanced backup topics with realistic current events for 2025
        error_log('News X AI Generator: Using backup topics with current 2025 context');
        return array(
            'Breaking: AI Regulation and Policy Developments in 2025',
            'Global Economic Outlook: Post-Pandemic Recovery and New Challenges',
            'Healthcare Innovation: Breakthrough Technologies Transforming Patient Care',
            'Climate Action: International Agreements and Renewable Energy Progress',
            'Space Exploration: Latest Missions and Discoveries in 2025',
            'Digital Transformation: How Technology is Reshaping Industries',
            'Cybersecurity Threats: Emerging Risks and Protective Measures',
            'Supply Chain Evolution: Global Trade and Logistics in 2025',
            'Social Media Regulation: Privacy and Content Moderation Policies',
            'Education Technology: Remote Learning and Digital Classrooms'
        );
    }
    
    /**
     * Get content length in tokens
     */
    private function get_content_length_tokens() {
        $length = get_option('news_x_ai_content_length', 'medium');
        
        switch ($length) {
            case 'short':
                return 2048;
            case 'long':
                return 4096;
            default:
                return 3072;
        }
    }
    
    /**
     * Get word count for content length
     */
    private function get_word_count($length) {
        switch ($length) {
            case 'short':
                return '300-500 words';
            case 'long':
                return '800-1200 words';
            default:
                return '500-800 words';
        }
    }
    
    /**
     * Test API connection
     */
    public function test_api_connection() {
        error_log('News X AI Generator: test_api_connection called');
        
        if (empty($this->api_key)) {
            error_log('News X AI Generator: API key is empty in test_api_connection');
            return array(
                'success' => false,
                'message' => 'API key not configured'
            );
        }
        
        error_log('News X AI Generator: Testing API with key length: ' . strlen($this->api_key));
        
        // Try gemini-2.5-flash first (free tier), then gemini-2.5-pro, then fallback to gemini-1.5-flash
        $models = array('gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-1.5-flash');
        $last_error = '';
        
        foreach ($models as $model) {
            error_log('News X AI Generator: Testing model: ' . $model);
            
            $response = wp_remote_post("https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent", array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $this->api_key
                ),
                'body' => json_encode(array(
                    'contents' => array(
                        array(
                            'parts' => array(
                                array('text' => 'Hello, this is a test message.')
                            )
                        )
                    ),
                    'generationConfig' => array(
                        'maxOutputTokens' => 10
                    )
                )),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                $last_error = $response->get_error_message();
                error_log('News X AI Generator: wp_remote_post error in test_api_connection with model ' . $model . ': ' . $last_error);
                continue;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            error_log('News X AI Generator: Test API response status for model ' . $model . ': ' . wp_remote_retrieve_response_code($response));
            error_log('News X AI Generator: Test API response body length for model ' . $model . ': ' . strlen($body));
            error_log('News X AI Generator: Test API response body for model ' . $model . ': ' . $body);
            
            if (empty($data) || isset($data['error'])) {
                $error_message = 'Unknown API error';
                if (isset($data['error']['message'])) {
                    $error_message = $data['error']['message'];
                }
                $last_error = $error_message;
                error_log('News X AI Generator: Test API error with model ' . $model . ': ' . $error_message);
                continue;
            }
            
            // Check if we have a valid response
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                error_log('News X AI Generator: API test successful with model ' . $model);
                return array(
                    'success' => true,
                    'message' => 'API connection successful with model ' . $model
                );
            } else {
                $last_error = 'Invalid response format from Gemini API';
                error_log('News X AI Generator: Invalid response format from Gemini API with model ' . $model);
                continue;
            }
        }
        
        // If we get here, all models failed
        error_log('News X AI Generator: All models failed in test. Last error: ' . $last_error);
        return array(
            'success' => false,
            'message' => $last_error
        );
    }
    
    /**
     * Determine and create category for the post
     */
    private function determine_and_create_category($title, $content, $topic) {
        error_log('News X AI Generator: Determining category for post: ' . $title);
        
        // Define category mapping based on keywords with weighted scoring
        $category_keywords = array(
            'Politics' => array(
                'primary' => array('politics', 'political', 'government', 'election', 'policy', 'legislation', 'congress', 'senate', 'president', 'minister', 'parliament', 'democracy', 'republican', 'democrat', 'campaign', 'vote', 'administration', 'law', 'bill', 'act'),
                'secondary' => array('announces', 'passed', 'signed', 'approved', 'voted', 'debate', 'hearing', 'committee', 'representative', 'senator', 'governor', 'mayor', 'official')
            ),
            'Technology' => array(
                'primary' => array('technology', 'tech', 'ai', 'artificial intelligence', 'machine learning', 'software', 'digital', 'innovation', 'startup', 'cybersecurity', 'blockchain', 'automation', 'robotics', 'virtual reality', 'augmented reality', 'algorithm', 'app', 'platform', 'system'),
                'secondary' => array('development', 'launch', 'release', 'update', 'version', 'feature', 'integration', 'api', 'database', 'server', 'cloud', 'mobile', 'web')
            ),
            'Business' => array(
                'primary' => array('business', 'economy', 'economic', 'market', 'stock', 'finance', 'financial', 'investment', 'trading', 'corporate', 'company', 'enterprise', 'startup', 'entrepreneur', 'commerce', 'trade', 'revenue', 'profit', 'earnings', 'quarterly'),
                'secondary' => array('merger', 'acquisition', 'ipo', 'funding', 'venture', 'capital', 'startup', 'growth', 'expansion', 'strategy', 'ceo', 'executive')
            ),
            'Health' => array(
                'primary' => array('health', 'medical', 'medicine', 'healthcare', 'hospital', 'doctor', 'patient', 'treatment', 'disease', 'vaccine', 'pharmaceutical', 'biotechnology', 'clinical', 'research', 'wellness', 'fitness', 'therapy', 'diagnosis', 'surgery'),
                'secondary' => array('study', 'trial', 'drug', 'medication', 'prescription', 'insurance', 'coverage', 'premium', 'deductible')
            ),
            'Science' => array(
                'primary' => array('science', 'scientific', 'research', 'study', 'discovery', 'experiment', 'laboratory', 'scientist', 'physics', 'chemistry', 'biology', 'astronomy', 'space', 'climate', 'environment', 'genetics', 'theory', 'hypothesis', 'data'),
                'secondary' => array('published', 'journal', 'peer-reviewed', 'findings', 'conclusion', 'methodology', 'analysis')
            ),
            'Sports' => array(
                'primary' => array('sports', 'football', 'basketball', 'baseball', 'soccer', 'tennis', 'olympics', 'athlete', 'team', 'championship', 'tournament', 'league', 'coach', 'player', 'game', 'match', 'season', 'playoff', 'final'),
                'secondary' => array('score', 'win', 'loss', 'victory', 'defeat', 'record', 'statistics', 'performance', 'training', 'injury')
            ),
            'Entertainment' => array(
                'primary' => array('entertainment', 'movie', 'film', 'music', 'celebrity', 'actor', 'actress', 'singer', 'artist', 'hollywood', 'television', 'tv', 'show', 'concert', 'award', 'festival', 'album', 'song', 'performance'),
                'secondary' => array('premiere', 'release', 'trailer', 'review', 'rating', 'box office', 'streaming', 'platform', 'series', 'episode')
            ),
            'Education' => array(
                'primary' => array('education', 'school', 'university', 'college', 'student', 'teacher', 'academic', 'learning', 'curriculum', 'degree', 'scholarship', 'research', 'campus', 'classroom', 'study', 'course', 'program', 'faculty'),
                'secondary' => array('enrollment', 'graduation', 'tuition', 'financial aid', 'admission', 'application', 'semester', 'grade', 'test')
            ),
            'Travel' => array(
                'primary' => array('travel', 'tourism', 'vacation', 'destination', 'hotel', 'airline', 'flight', 'trip', 'journey', 'adventure', 'explore', 'tourist', 'resort', 'beach', 'mountain', 'city', 'booking', 'reservation'),
                'secondary' => array('airport', 'passport', 'visa', 'luggage', 'itinerary', 'guide', 'tour', 'excursion', 'cruise', 'road trip')
            ),
            'Food' => array(
                'primary' => array('food', 'restaurant', 'cuisine', 'cooking', 'chef', 'recipe', 'dining', 'meal', 'kitchen', 'culinary', 'gastronomy', 'nutrition', 'diet', 'ingredient', 'flavor', 'taste', 'menu', 'dish'),
                'secondary' => array('review', 'rating', 'award', 'michelin', 'star', 'organic', 'local', 'farm', 'market', 'delivery')
            ),
            'Environment' => array(
                'primary' => array('environment', 'climate', 'weather', 'pollution', 'conservation', 'sustainability', 'renewable', 'energy', 'green', 'eco-friendly', 'carbon', 'emission', 'wildlife', 'nature', 'forest', 'ocean', 'recycling'),
                'secondary' => array('global warming', 'climate change', 'extinction', 'endangered', 'habitat', 'preservation', 'clean energy', 'solar', 'wind')
            ),
            'Social Issues' => array(
                'primary' => array('social', 'society', 'community', 'human rights', 'equality', 'diversity', 'inclusion', 'justice', 'activism', 'protest', 'movement', 'advocacy', 'charity', 'volunteer', 'nonprofit', 'discrimination'),
                'secondary' => array('awareness', 'campaign', 'support', 'donation', 'fundraising', 'initiative', 'program', 'service', 'help', 'assistance')
            ),
            'International' => array(
                'primary' => array('international', 'global', 'world', 'foreign', 'diplomacy', 'treaty', 'alliance', 'conflict', 'peace', 'war', 'military', 'defense', 'security', 'border', 'immigration', 'refugee', 'embassy'),
                'secondary' => array('summit', 'meeting', 'negotiation', 'agreement', 'sanction', 'trade', 'export', 'import', 'tariff', 'embargo')
            )
        );
        
        // Combine title, content, and topic for analysis
        $text_to_analyze = strtolower($title . ' ' . strip_tags($content) . ' ' . (is_string($topic) ? $topic : ''));
        
        // Score each category based on keyword matches with weighted scoring
        $category_scores = array();
        foreach ($category_keywords as $category_name => $keyword_groups) {
            $score = 0;
            
            // Primary keywords get higher weight (3 points each)
            foreach ($keyword_groups['primary'] as $keyword) {
                $count = substr_count($text_to_analyze, strtolower($keyword));
                $score += ($count * 3);
            }
            
            // Secondary keywords get lower weight (1 point each)
            foreach ($keyword_groups['secondary'] as $keyword) {
                $count = substr_count($text_to_analyze, strtolower($keyword));
                $score += $count;
            }
            
            // Title keywords get extra weight (multiply by 2)
            $title_lower = strtolower($title);
            foreach ($keyword_groups['primary'] as $keyword) {
                $count = substr_count($title_lower, strtolower($keyword));
                $score += ($count * 6); // Extra weight for title matches
            }
            
            if ($score > 0) {
                $category_scores[$category_name] = $score;
            }
        }
        
        // If no category matches, use AI to determine category
        if (empty($category_scores)) {
            $category_name = $this->determine_category_with_ai($title, $content, $topic);
        } else {
            // Get the category with the highest score
            arsort($category_scores);
            $category_name = array_keys($category_scores)[0];
            
            // Log the scoring for debugging
            error_log('News X AI Generator: Category scores: ' . json_encode($category_scores));
        }
        
        error_log('News X AI Generator: Determined category: ' . $category_name);
        
        // Check if category exists, if not create it
        $category_id = $this->get_or_create_category($category_name);
        
        return $category_id;
    }
    
    /**
     * Use AI to determine category when keyword matching fails
     */
    private function determine_category_with_ai($title, $content, $topic) {
        error_log('News X AI Generator: Using AI to determine category');
        
        $prompt = "Analyze this news article and determine the most appropriate category from this list: Technology, Politics, Business, Health, Science, Sports, Entertainment, Education, Travel, Food, Environment, Social Issues, International.\n\n";
        $prompt .= "Title: " . $title . "\n";
        $prompt .= "Content: " . substr(strip_tags($content), 0, 500) . "...\n";
        $prompt .= "Topic: " . (is_string($topic) ? $topic : 'General News') . "\n\n";
        $prompt .= "Respond with only the category name, nothing else.";
        
        // Try to get AI response
        $models = array('gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-1.5-flash');
        
        foreach ($models as $model) {
            $response = wp_remote_post("https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent", array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $this->api_key
                ),
                'body' => json_encode(array(
                    'contents' => array(
                        array(
                            'parts' => array(
                                array('text' => $prompt)
                            )
                        )
                    ),
                    'generationConfig' => array(
                        'temperature' => 0.3,
                        'maxOutputTokens' => 50
                    )
                )),
                'timeout' => 30
            ));
            
            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                    $ai_category = trim($data['candidates'][0]['content']['parts'][0]['text']);
                    error_log('News X AI Generator: AI determined category: ' . $ai_category);
                    return $ai_category;
                }
            }
        }
        
        // Fallback to default category
        error_log('News X AI Generator: AI category determination failed, using default');
        return 'General';
    }
    
    /**
     * Get or create category
     */
    private function get_or_create_category($category_name) {
        // Check if category exists
        $existing_category = get_term_by('name', $category_name, 'category');
        
        if ($existing_category) {
            error_log('News X AI Generator: Category exists: ' . $category_name . ' (ID: ' . $existing_category->term_id . ')');
            return $existing_category->term_id;
        }
        
        // Create new category
        $new_category = wp_insert_term($category_name, 'category');
        
        if (is_wp_error($new_category)) {
            error_log('News X AI Generator: Failed to create category: ' . $category_name . ' - ' . $new_category->get_error_message());
            // Fallback to default category
            return get_option('default_category');
        }
        
        error_log('News X AI Generator: Created new category: ' . $category_name . ' (ID: ' . $new_category['term_id'] . ')');
        return $new_category['term_id'];
    }
} 