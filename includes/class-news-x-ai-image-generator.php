<?php
/**
 * News X AI Image Generator
 * 
 * Handles image generation and management for the News X AI Generator plugin.
 */

// Ensure required WordPress functions are available
if (!function_exists('media_handle_sideload')) {
    require_once(ABSPATH . 'wp-admin/includes/media.php');
}
if (!function_exists('download_url')) {
    require_once(ABSPATH . 'wp-admin/includes/file.php');
}

class NewsX_AI_Image_Generator {
    
    private $image_source;
    private $image_quality;
    
    public function __construct() {
        $this->image_source = get_option('news_x_ai_image_source', 'gemini'); // Default to Gemini
        $this->image_quality = get_option('news_x_ai_image_quality', 'high');
    }
    
    /**
     * Generate image for post
     */
    public function generate_image($title, $topic) {
        error_log('News X AI Generator: Image generation started for title: ' . $title . ', topic: ' . $topic);
        
        // Try Gemini image generation first
        $result = $this->generate_gemini_image($title, $topic);
        
        if ($result['success']) {
            error_log('News X AI Generator: Gemini image generation successful');
            return $result;
        }
        
        // Fallback to other sources if Gemini fails
        error_log('News X AI Generator: Gemini image generation failed, trying fallback sources');
        
        $search_query = $this->build_search_query($title, $topic);
        error_log('News X AI Generator: Image search query: ' . $search_query);
        
        switch ($this->image_source) {
            case 'gemini':
                $result = $this->generate_gemini_image($title, $topic);
                break;
            case 'unsplash':
                $result = $this->get_unsplash_image($search_query);
                break;
            case 'pexels':
                $result = $this->get_pexels_image($search_query);
                break;
            case 'pixabay':
                $result = $this->get_pixabay_image($search_query);
                break;
            default:
                $result = $this->generate_gemini_image($title, $topic);
                break;
        }
        
        error_log('News X AI Generator: Image generation completed - success: ' . ($result['success'] ? 'true' : 'false') . ', source: ' . ($result['source'] ?? 'unknown') . ', URL: ' . ($result['image_url'] ?? 'none'));
        
        return $result;
    }
    
    /**
     * Generate image using Free AI Image Generation Services
     */
    private function generate_gemini_image($title, $topic) {
        $image_prompt = $this->build_simple_prompt($title, $topic);
        error_log('News X AI Generator: AI image prompt: ' . $image_prompt);
        
        // Clean and encode the prompt
        $cleaned_prompt = $this->clean_prompt_for_url($image_prompt);
        
        // Generate unique seeds to prevent caching issues
        $timestamp = time();
        $microseconds = microtime(true);
        $unique_seed = $timestamp . '-' . substr(str_replace('.', '', $microseconds), -6);
        $random_seed = rand(10000, 99999);
        $cache_buster = md5($title . $topic . $timestamp);
        
        // Try AI image generation services first
        $services = array(
            // Service 1: Pollinations AI - Primary AI image generator
            array(
                'name' => 'Pollinations AI',
                'type' => 'ai_generation',
                'url' => "https://image.pollinations.ai/prompt/" . urlencode($cleaned_prompt) . "?width=1920&height=1080&seed=" . $unique_seed . "&nologo=true&enhance=true&quality=high&style=photographic"
            ),
            // Service 2: Stable Diffusion XL via HuggingFace
            array(
                'name' => 'Stable Diffusion',
                'type' => 'ai_generation',
                'url' => "https://api-inference.huggingface.co/models/stabilityai/stable-diffusion-xl-base-1.0",
                'headers' => array(
                    'Authorization' => 'Bearer ' . getenv('HUGGINGFACE_API_KEY')
                ),
                'payload' => array(
                    'inputs' => $cleaned_prompt,
                    'parameters' => array(
                        'negative_prompt' => 'text, watermark, logo, label, banner, title, words, letters, signature, timestamp, date',
                        'num_inference_steps' => 30,
                        'guidance_scale' => 7.5
                    )
                )
            ),
            // Service 3: Lexica Art - AI Image Generation
            array(
                'name' => 'Lexica',
                'type' => 'ai_generation',
                'url' => "https://lexica.art/api/v1/search?q=" . urlencode($cleaned_prompt)
            ),
            // Service 4: Unsplash - High quality stock photos as fallback
            array(
                'name' => 'Unsplash',
                'type' => 'stock_photo',
                'url' => "https://api.unsplash.com/photos/random?query=" . urlencode($cleaned_prompt) . "&orientation=landscape"
            ),
            // Service 5: Pexels - Another stock photo fallback
            array(
                'name' => 'Pexels',
                'type' => 'stock_photo',
                'url' => "https://api.pexels.com/v1/search?query=" . urlencode($cleaned_prompt) . "&orientation=landscape&per_page=1"
            ),
            // Final Fallback: Local SVG (only used if all else fails)
            array(
                'name' => 'Local Fallback',
                'type' => 'local',
                'url' => 'data:image/svg+xml;base64,' . base64_encode('
                    <svg width="1920" height="1080" xmlns="http://www.w3.org/2000/svg">
                        <rect width="1920" height="1080" fill="#2c3e50"/>
                        <text x="960" y="540" font-family="Arial" font-size="24" fill="white" text-anchor="middle" dominant-baseline="middle">Generating image...</text>
                    </svg>
                ')
            )
        );
        
        foreach ($services as $service) {
            error_log('News X AI Generator: Trying ' . $service['name'] . ' for image generation');
            
            try {
                switch ($service['type']) {
                    case 'ai_generation':
                        $result = $this->handle_ai_generation($service, $cleaned_prompt);
                        if ($result['success']) {
                            return $result;
                        }
                        break;

                    case 'stock_photo':
                        $result = $this->handle_stock_photo($service, $cleaned_prompt);
                        if ($result['success']) {
                            return $result;
                        }
                        break;

                    case 'local':
                        error_log('News X AI Generator: Using local fallback - ' . $service['name']);
                        return array(
                            'success' => true,
                            'image_url' => $service['url'],
                            'source' => 'local-fallback',
                            'photographer' => 'Local Generator',
                            'photographer_url' => ''
                        );
                }
            } catch (Exception $e) {
                error_log('News X AI Generator: Exception with ' . $service['name'] . ': ' . $e->getMessage());
                continue;
            }
        }
        
        // Final fallback - guaranteed to work
        error_log('News X AI Generator: All services failed, using guaranteed fallback');
        return array(
            'success' => true,
            'image_url' => "https://via.placeholder.com/1920x1080/34495E/FFFFFF?text=News+Image",
            'source' => 'placeholder-fallback',
            'photographer' => 'Placeholder Service',
            'photographer_url' => ''
        );
    }

    /**
     * Handle AI image generation services
     */
    private function handle_ai_generation($service, $prompt) {
        error_log('News X AI Generator: Handling AI generation for ' . $service['name']);
        
        if ($service['name'] === 'Pollinations AI') {
            // Pollinations AI uses direct URL generation
            return array(
                'success' => true,
                'image_url' => $service['url'],
                'source' => 'pollinations-ai',
                'photographer' => 'Pollinations AI',
                'photographer_url' => 'https://pollinations.ai'
            );
        }
        
        if ($service['name'] === 'Stable Diffusion') {
            // Make POST request to Hugging Face API
            $response = wp_remote_post($service['url'], array(
                'headers' => $service['headers'],
                'body' => json_encode($service['payload']),
                'timeout' => 30,
                'sslverify' => false
            ));
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $body = wp_remote_retrieve_body($response);
                if (!empty($body)) {
                    // Save the image data
                    $upload_dir = wp_upload_dir();
                    $filename = 'ai-generated-' . time() . '.jpg';
                    $filepath = $upload_dir['path'] . '/' . $filename;
                    
                    if (file_put_contents($filepath, base64_decode($body))) {
                        return array(
                            'success' => true,
                            'image_url' => $upload_dir['url'] . '/' . $filename,
                            'source' => 'stable-diffusion',
                            'photographer' => 'Stable Diffusion XL',
                            'photographer_url' => 'https://stability.ai'
                        );
                    }
                }
            }
        }
        
        if ($service['name'] === 'Lexica') {
            // Search Lexica for existing AI-generated images
            $response = wp_remote_get($service['url'], array(
                'timeout' => 15,
                'sslverify' => false
            ));
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if (!empty($data['images'][0]['url'])) {
                    return array(
                        'success' => true,
                        'image_url' => $data['images'][0]['url'],
                        'source' => 'lexica',
                        'photographer' => 'Lexica Art',
                        'photographer_url' => 'https://lexica.art'
                    );
                }
            }
        }
        
        return array('success' => false);
    }

    /**
     * Handle stock photo services
     */
    private function handle_stock_photo($service, $prompt) {
        error_log('News X AI Generator: Handling stock photo for ' . $service['name']);
        
        if ($service['name'] === 'Unsplash') {
            return $this->get_unsplash_image($prompt);
        }
        
        if ($service['name'] === 'Pexels') {
            return $this->get_pexels_image($prompt);
        }
        
        return array('success' => false);
    }
    
    /**
     * Build simple image generation prompt
     */
    private function build_simple_prompt($title, $topic) {
        // Convert to lowercase for easier pattern matching
        $title_lower = strtolower($title . ' ' . $topic);
        
        // Initialize arrays for building the prompt
        $scene_type = '';
        $main_elements = array();
        $style_elements = array(
            'professional photojournalism',
            'high quality editorial photo',
            'sharp focus',
            '16:9 aspect ratio',
            'dramatic lighting'
        );
        
        // Political / Government Content
        if (preg_match('/\b(capitol|congress|senate|house|parliament|government|politic|election|campaign|policy|diplomatic|global stage)\b/i', $title_lower)) {
            $scene_type = 'political press photography';
            $main_elements = array(
                'US Capitol Building in Washington DC',
                'American flags waving',
                'government officials in formal attire',
                'marble columns and architecture',
                'serious political atmosphere',
                'press conference podiums'
            );
        }
        // International / Global News
        elseif (preg_match('/\b(global|international|world|foreign|diplomatic|summit|united nations|treaty)\b/i', $title_lower)) {
            $scene_type = 'international diplomacy photo';
            $main_elements = array(
                'United Nations headquarters',
                'world leaders meeting',
                'diplomatic handshakes',
                'international flags',
                'formal summit setting'
            );
        }
        // Technology / Innovation
        elseif (preg_match('/\b(technology|tech|ai|artificial intelligence|robot|digital|innovation|startup|cyber|software|computing)\b/i', $title_lower)) {
            $scene_type = 'technology news photography';
            $main_elements = array(
                'modern tech laboratory',
                'advanced robotics',
                'holographic displays',
                'scientists working',
                'cutting-edge equipment'
            );
        }
        // Economy / Business / Finance
        elseif (preg_match('/\b(economy|economic|finance|market|stock|trade|investment|business|corporate|industry)\b/i', $title_lower)) {
            $scene_type = 'financial news photography';
            $main_elements = array(
                'Wall Street stock exchange',
                'business district skyscrapers',
                'financial data displays',
                'business professionals',
                'modern corporate environment'
            );
        }
        // Health / Medical
        elseif (preg_match('/\b(health|medical|medicine|vaccine|hospital|doctor|patient|healthcare|pandemic|virus)\b/i', $title_lower)) {
            $scene_type = 'healthcare news photography';
            $main_elements = array(
                'modern hospital setting',
                'medical professionals at work',
                'advanced medical equipment',
                'laboratory research',
                'healthcare facility'
            );
        }
        // Environmental / Climate
        elseif (preg_match('/\b(environment|climate|green|renewable|energy|sustainability|carbon|nature|pollution)\b/i', $title_lower)) {
            $scene_type = 'environmental journalism photo';
            $main_elements = array(
                'renewable energy installation',
                'dramatic natural landscape',
                'environmental impact',
                'climate scientists',
                'weather phenomena'
            );
        }
        // Sports / Athletics
        elseif (preg_match('/\b(sport|game|team|player|league|tournament|championship|olympic|athlete)\b/i', $title_lower)) {
            $scene_type = 'sports photography';
            $main_elements = array(
                'stadium atmosphere',
                'athletes in action',
                'dramatic sports moment',
                'cheering crowds',
                'victory celebration'
            );
        }
        // Entertainment / Culture
        elseif (preg_match('/\b(entertainment|movie|film|music|celebrity|culture|art|fashion|performance)\b/i', $title_lower)) {
            $scene_type = 'entertainment news photo';
            $main_elements = array(
                'red carpet event',
                'stage performance',
                'celebrity appearance',
                'cultural celebration',
                'artistic display'
            );
        }
        // Default / General News
        else {
            $scene_type = 'news photography';
            $main_elements = array(
                'professional news setting',
                'press conference room',
                'journalist interviews',
                'media coverage',
                'current events'
            );
        }
        
        // Build the final prompt
        $prompt_parts = array_merge(
            array($scene_type),
            array_slice($main_elements, 0, 3),  // Take top 3 main elements
            array_slice($style_elements, 0, 3)  // Take top 3 style elements
        );
        
        // Add specific keywords from title (up to 2 important ones)
        $title_keywords = array_filter(
            explode(' ', $title_lower),
            function($word) {
                return strlen($word) > 3 && 
                       !in_array($word, array('the', 'and', 'for', 'are', 'but', 'not', 'you', 'all', 'any', 'can', 'had', 'has', 'her', 'his', 'was', 'one', 'our', 'out', 'who', 'why', 'how', 'what', 'when', 'where', 'which', 'with', 'would', 'could', 'should', 'have', 'this', 'that', 'these', 'those', 'from', 'into', 'after', 'before', 'during', 'under', 'over', 'again'));
            }
        );
        
        $important_keywords = array_slice($title_keywords, 0, 2);
        if (!empty($important_keywords)) {
            $prompt_parts = array_merge($prompt_parts, $important_keywords);
        }
        
        // Combine all parts with proper formatting
        $prompt = implode(', ', array_filter($prompt_parts));
        
        error_log('News X AI Generator: Generated image prompt: ' . $prompt);
        
        return $prompt;
    }
    
    /**
     * Clean prompt for URL encoding
     */
    private function clean_prompt_for_url($prompt) {
        // Remove newlines and extra spaces
        $cleaned = preg_replace('/\s+/', ' ', trim($prompt));
        
        // Remove special characters that might cause URL issues
        $cleaned = preg_replace('/[^\w\s\-.,!?]/', '', $cleaned);
        
        // Limit length to avoid URL too long errors
        if (strlen($cleaned) > 200) {
            $cleaned = substr($cleaned, 0, 200);
        }
        
        return $cleaned;
    }
    
    /**
     * Get image from Unsplash
     */
    private function get_unsplash_image($query) {
        // Use Unsplash's public API with a demo access key (free tier)
        // For production, get your own key from https://unsplash.com/developers
        $access_key = 'demo'; // This will use Unsplash's demo API
        
        $url = 'https://api.unsplash.com/photos/random?' . http_build_query(array(
            'query' => $query,
            'orientation' => 'landscape',
            'count' => 1
        ));
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Client-ID ' . $access_key
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return $this->get_fallback_image($query);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data) || !isset($data[0]['urls']['regular'])) {
            return $this->get_fallback_image($query);
        }
        
        $image_url = $data[0]['urls']['regular'];
        
        // Get high quality version if requested
        if ($this->image_quality === 'high') {
            $image_url = $data[0]['urls']['full'];
        }
        
        return array(
            'success' => true,
            'image_url' => $image_url,
            'source' => 'unsplash',
            'photographer' => $data[0]['user']['name'] ?? '',
            'photographer_url' => $data[0]['user']['links']['html'] ?? ''
        );
    }
    
    /**
     * Get image from Pexels
     */
    private function get_pexels_image($query) {
        // For now, use fallback since Pexels requires API key
        // You can get a free key from https://pexels.com/api/
        return $this->get_fallback_image($query);
    }
    
    /**
     * Get fallback image from placeholder services
     */
    private function get_fallback_image($query) {
        // Use consistent high-quality images with proper dimensions
        $width = 1920;  // Always use high quality
        $height = 1080; // 16:9 aspect ratio for featured images
        
        // Use Picsum Photos for high-quality placeholder images
        // Add a seed based on the query to get consistent images for similar topics
        $seed = crc32($query) % 1000; // Generate consistent seed for same query
        
        $image_url = "https://picsum.photos/{$width}/{$height}?" . http_build_query(array(
            'random' => $seed,
            'blur' => 0
        ));
        
        return array(
            'success' => true,
            'image_url' => $image_url,
            'source' => 'picsum',
            'photographer' => 'Picsum Photos',
            'photographer_url' => 'https://picsum.photos'
        );
    }
    
    /**
     * Download and save image to media library
     */
    public function download_image($image_url, $post_id, $title = '') {
        error_log('News X AI Generator: Downloading image from URL: ' . $image_url . ' for post: ' . $post_id);
        
        if (empty($image_url)) {
            error_log('News X AI Generator: Image URL is empty');
            return false;
        }
        
        // Download the image with better error handling
        $tmp = download_url($image_url, 300, false);  // 5 minutes timeout, no verify SSL
        
        if (is_wp_error($tmp)) {
            error_log('News X AI Generator: Failed to download image: ' . $tmp->get_error_message());
            
            // Try alternative download method
            $image_data = wp_remote_get($image_url, array(
                'timeout' => 60,
                'sslverify' => false,
                'user-agent' => 'WordPress/News-X-AI-Generator'
            ));
            
            if (!is_wp_error($image_data) && wp_remote_retrieve_response_code($image_data) === 200) {
                $body = wp_remote_retrieve_body($image_data);
                if (!empty($body)) {
                    // Save to temp file
                    $upload_dir = wp_upload_dir();
                    $tmp = $upload_dir['path'] . '/temp_' . time() . '.jpg';
                    if (file_put_contents($tmp, $body)) {
                        error_log('News X AI Generator: Downloaded image via alternative method');
                    } else {
                        error_log('News X AI Generator: Failed to save downloaded image data');
                        return false;
                    }
                } else {
                    error_log('News X AI Generator: Downloaded image body is empty');
                    return false;
                }
            } else {
                error_log('News X AI Generator: Alternative download method also failed');
                return false;
            }
        }
        
        // Prepare file array
        $file_array = array(
            'name' => sanitize_file_name($title ?: 'news-image-' . time()) . '.jpg',
            'tmp_name' => $tmp
        );
        
        // Move the temporary file into the uploads directory
        $id = media_handle_sideload($file_array, $post_id);
        
        // Clean up the temporary file
        @unlink($tmp);
        
        if (is_wp_error($id)) {
            error_log('News X AI Generator: Failed to handle media sideload: ' . $id->get_error_message());
            return false;
        }
        
        error_log('News X AI Generator: Image downloaded successfully, attachment ID: ' . $id);
        return $id;
    }
    
    /**
     * Get image dimensions based on quality setting
     */
    private function get_image_dimensions() {
        switch ($this->image_quality) {
            case 'high':
                return array('width' => 1920, 'height' => 1080);
            case 'medium':
            default:
                return array('width' => 1280, 'height' => 720);
        }
    }
    
    /**
     * Generate image alt text
     */
    public function generate_alt_text($title, $topic) {
        // Simple alt text generation based on title and topic
        $alt_text = $title;
        
        // Add topic context if different from title
        if (strpos(strtolower($title), strtolower($topic)) === false) {
            $alt_text .= ' - ' . $topic;
        }
        
        return $alt_text;
    }
    
    /**
     * Validate image URL
     */
    public function validate_image_url($url) {
        $response = wp_remote_head($url, array(
            'timeout' => 10,
            'redirection' => 5
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        
        return strpos($content_type, 'image/') === 0;
    }
    
    /**
     * Get image source information
     */
    public function get_image_source_info() {
        $sources = array(
            'gemini' => array(
                'name' => 'Free AI Image Generators',
                'url' => 'https://pollinations.ai',
                'api_url' => 'https://pollinations.ai',
                'free_tier' => 'Unlimited'
            ),
            'unsplash' => array(
                'name' => 'Unsplash',
                'url' => 'https://unsplash.com',
                'api_url' => 'https://unsplash.com/developers',
                'free_tier' => '1000 requests per hour'
            ),
            'pexels' => array(
                'name' => 'Pexels',
                'url' => 'https://pexels.com',
                'api_url' => 'https://pexels.com/api/',
                'free_tier' => '200 requests per hour'
            ),
            'pixabay' => array(
                'name' => 'Pixabay',
                'url' => 'https://pixabay.com',
                'api_url' => 'https://pixabay.com/api/docs/',
                'free_tier' => '5000 requests per hour'
            )
        );
        
        return isset($sources[$this->image_source]) ? $sources[$this->image_source] : $sources['gemini'];
    }
}