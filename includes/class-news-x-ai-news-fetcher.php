<?php
/**
 * News X AI News Fetcher
 * 
 * Fetches real news from various sources to use as input for AI generation
 */

if (!defined('ABSPATH')) {
    exit;
}

class NewsX_AI_News_Fetcher {
    
    private $news_sources = array(
        'newsapi' => 'https://newsapi.org/v2/',
        'gnews' => 'https://gnews.io/api/v4/',
        'mediastack' => 'http://api.mediastack.com/v1/'
    );
    
    /**
     * Get current news topics and facts
     */
    public function get_current_news($category = '') {
        $news_items = array();
        
        // Try multiple news APIs for redundancy
        foreach ($this->news_sources as $source => $base_url) {
            $items = $this->fetch_from_source($source, $base_url, $category);
            if (!empty($items)) {
                $news_items = array_merge($news_items, $items);
            }
        }
        
        // Fallback to RSS feeds if APIs fail
        if (empty($news_items)) {
            $news_items = $this->fetch_from_rss_feeds($category);
        }
        
        return $this->process_news_items($news_items);
    }
    
    /**
     * Fetch news from a specific source
     */
    private function fetch_from_source($source, $base_url, $category) {
        switch ($source) {
            case 'newsapi':
                return $this->fetch_from_newsapi($base_url, $category);
            case 'gnews':
                return $this->fetch_from_gnews($base_url, $category);
            case 'mediastack':
                return $this->fetch_from_mediastack($base_url, $category);
            default:
                return array();
        }
    }
    
    /**
     * Fetch from NewsAPI.org
     */
    private function fetch_from_newsapi($base_url, $category) {
        $api_key = getenv('NEWSAPI_KEY') ?: 'demo'; // Use demo key for testing
        $endpoint = $base_url . 'top-headlines';
        
        $params = array(
            'apiKey' => $api_key,
            'language' => 'en',
            'pageSize' => 10,
            'sortBy' => 'publishedAt' // Get most recent first
        );
        
        if (!empty($category)) {
            $params['category'] = $category;
        }
        
        $response = wp_remote_get(add_query_arg($params, $endpoint));
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data['articles'])) {
            return array();
        }
        
        // Filter for recent articles (last 30 days)
        $current_time = time();
        $thirty_days_ago = $current_time - (30 * 24 * 60 * 60);
        
        $recent_articles = array_filter($data['articles'], function($article) use ($thirty_days_ago) {
            if (empty($article['publishedAt'])) {
                return false;
            }
            $article_time = strtotime($article['publishedAt']);
            return $article_time >= $thirty_days_ago;
        });
        
        if (empty($recent_articles)) {
            return array();
        }
        
        return array_map(function($article) {
            return array(
                'title' => $article['title'],
                'description' => $article['description'],
                'content' => $article['content'],
                'url' => $article['url'],
                'source' => $article['source']['name'],
                'published' => $article['publishedAt'],
                'category' => $article['category'] ?? '',
                'facts' => $this->extract_facts($article['content'])
            );
        }, $recent_articles);
    }
    
    /**
     * Fetch from GNews
     */
    private function fetch_from_gnews($base_url, $category) {
        $api_key = getenv('GNEWS_KEY') ?: 'demo';
        $endpoint = $base_url . 'top-headlines';
        
        $params = array(
            'token' => $api_key,
            'lang' => 'en',
            'max' => 10,
            'sortby' => 'publishedAt' // Get most recent first
        );
        
        if (!empty($category)) {
            $params['topic'] = $category;
        }
        
        $response = wp_remote_get(add_query_arg($params, $endpoint));
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data['articles'])) {
            return array();
        }
        
        // Filter for recent articles (last 30 days)
        $current_time = time();
        $thirty_days_ago = $current_time - (30 * 24 * 60 * 60);
        
        $recent_articles = array_filter($data['articles'], function($article) use ($thirty_days_ago) {
            if (empty($article['publishedAt'])) {
                return false;
            }
            $article_time = strtotime($article['publishedAt']);
            return $article_time >= $thirty_days_ago;
        });
        
        if (empty($recent_articles)) {
            return array();
        }
        
        return array_map(function($article) {
            return array(
                'title' => $article['title'],
                'description' => $article['description'],
                'content' => $article['content'],
                'url' => $article['url'],
                'source' => $article['source']['name'],
                'published' => $article['publishedAt'],
                'category' => $article['topic'] ?? '',
                'facts' => $this->extract_facts($article['content'])
            );
        }, $recent_articles);
    }
    
    /**
     * Fetch from MediaStack
     */
    private function fetch_from_mediastack($base_url, $category) {
        $api_key = getenv('MEDIASTACK_KEY') ?: 'demo';
        $endpoint = $base_url . 'news';
        
        $params = array(
            'access_key' => $api_key,
            'languages' => 'en',
            'limit' => 10,
            'sort' => 'published_desc' // Get most recent first
        );
        
        if (!empty($category)) {
            $params['categories'] = $category;
        }
        
        $response = wp_remote_get(add_query_arg($params, $endpoint));
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data['data'])) {
            return array();
        }
        
        // Filter for recent articles (last 30 days)
        $current_time = time();
        $thirty_days_ago = $current_time - (30 * 24 * 60 * 60);
        
        $recent_articles = array_filter($data['data'], function($article) use ($thirty_days_ago) {
            if (empty($article['published_at'])) {
                return false;
            }
            $article_time = strtotime($article['published_at']);
            return $article_time >= $thirty_days_ago;
        });
        
        if (empty($recent_articles)) {
            return array();
        }
        
        return array_map(function($article) {
            return array(
                'title' => $article['title'],
                'description' => $article['description'],
                'content' => $article['content'],
                'url' => $article['url'],
                'source' => $article['source'],
                'published' => $article['published_at'],
                'category' => $article['category'],
                'facts' => $this->extract_facts($article['content'])
            );
        }, $recent_articles);
    }
    
    /**
     * Fetch from RSS feeds as fallback
     */
    private function fetch_from_rss_feeds($category) {
        $feeds = $this->get_rss_feeds($category);
        $news_items = array();
        
        foreach ($feeds as $feed_url) {
            $rss = fetch_feed($feed_url);
            
            if (is_wp_error($rss)) {
                continue;
            }
            
            $maxitems = $rss->get_item_quantity(5);
            $items = $rss->get_items(0, $maxitems);
            
            foreach ($items as $item) {
                $news_items[] = array(
                    'title' => $item->get_title(),
                    'description' => $item->get_description(),
                    'content' => $item->get_content(),
                    'url' => $item->get_permalink(),
                    'source' => $rss->get_title(),
                    'published' => $item->get_date('Y-m-d H:i:s'),
                    'category' => $category,
                    'facts' => $this->extract_facts($item->get_content())
                );
            }
        }
        
        return $news_items;
    }
    
    /**
     * Get RSS feeds by category
     */
    private function get_rss_feeds($category) {
        $feeds = array(
            'general' => array(
                'http://rss.cnn.com/rss/cnn_topstories.rss',
                'http://feeds.bbci.co.uk/news/rss.xml'
            ),
            'technology' => array(
                'http://feeds.feedburner.com/TechCrunch',
                'https://www.wired.com/feed/rss'
            ),
            'business' => array(
                'http://feeds.reuters.com/reuters/businessNews',
                'http://feeds.marketwatch.com/marketwatch/topstories/'
            ),
            'science' => array(
                'http://feeds.sciencedaily.com/sciencedaily',
                'https://www.sciencemag.org/rss/news_current.xml'
            ),
            'health' => array(
                'http://rssfeeds.webmd.com/rss/rss.aspx?RSSSource=RSS_PUBLIC',
                'https://www.medicalnewstoday.com/newsfeeds-rss'
            ),
            'politics' => array(
                'http://feeds.washingtonpost.com/rss/politics',
                'http://rss.politico.com/politics-news.xml'
            )
        );
        
        return isset($feeds[$category]) ? $feeds[$category] : $feeds['general'];
    }
    
    /**
     * Extract key facts from content
     */
    private function extract_facts($content) {
        $facts = array();
        
        // Extract dates
        preg_match_all('/\b\d{1,2}[\s\/\-\.]\d{1,2}[\s\/\-\.]\d{2,4}\b/', $content, $dates);
        if (!empty($dates[0])) {
            $facts['dates'] = array_unique($dates[0]);
        }
        
        // Extract statistics/numbers
        preg_match_all('/\b\d+(?:,\d{3})*(?:\.\d+)?%?\b/', $content, $numbers);
        if (!empty($numbers[0])) {
            $facts['statistics'] = array_unique($numbers[0]);
        }
        
        // Extract quotes
        preg_match_all('/"([^"]+)"/', $content, $quotes);
        if (!empty($quotes[1])) {
            $facts['quotes'] = array_unique($quotes[1]);
        }
        
        // Extract organizations
        $org_patterns = array(
            '/\b(?:[A-Z][a-z]+\s+)*[A-Z][a-z]*(?:\s+(?:Inc|Corp|Ltd|LLC|Company|Association|Organization))\b/',
            '/\b[A-Z][A-Z]+\b/' // Acronyms
        );
        
        $organizations = array();
        foreach ($org_patterns as $pattern) {
            preg_match_all($pattern, $content, $matches);
            if (!empty($matches[0])) {
                $organizations = array_merge($organizations, $matches[0]);
            }
        }
        
        if (!empty($organizations)) {
            $facts['organizations'] = array_unique($organizations);
        }
        
        return $facts;
    }
    
    /**
     * Process and validate news items
     */
    private function process_news_items($items) {
        // Remove duplicates
        $seen_titles = array();
        $unique_items = array();
        
        foreach ($items as $item) {
            $title_hash = md5(strtolower($item['title']));
            if (!isset($seen_titles[$title_hash])) {
                $seen_titles[$title_hash] = true;
                $unique_items[] = $item;
            }
        }
        
        // Sort by date (newest first)
        usort($unique_items, function($a, $b) {
            return strtotime($b['published']) - strtotime($a['published']);
        });
        
        // Take top 10 most recent items
        return array_slice($unique_items, 0, 10);
    }
}