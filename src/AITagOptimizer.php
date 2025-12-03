<?php
namespace WpPlugin;

class AITagOptimizer
{
    private array $options;

    public function __construct()
    {
        $this->options = get_option('gd_autotag_options', []);
    }

    /**
     * Optimize tags using AI
     *
     * @param array $tags Generated tags from frequency analysis
     * @param string $content Post content for context
     * @param string $title Post title
     * @return array Optimized tags
     */
    public function optimize_tags(array $tags, string $content, string $title): array
    {
        // Check if AI optimization is enabled
        if (!$this->is_enabled()) {
            return $tags;
        }

        // Check if API key is configured
        if (empty($this->options['ai_api_key'])) {
            return $tags;
        }

        $provider = $this->options['ai_provider'] ?? 'openai';

        try {
            switch ($provider) {
                case 'openai':
                    return $this->optimize_with_openai($tags, $content, $title);
                case 'anthropic':
                    return $this->optimize_with_anthropic($tags, $content, $title);
                case 'google':
                    return $this->optimize_with_google($tags, $content, $title);
                case 'custom':
                    return $this->optimize_with_custom($tags, $content, $title);
                default:
                    return $tags;
            }
        } catch (\Exception $e) {
            // Log error if debug mode is enabled
            if (!empty($this->options['debug_mode'])) {
                error_log('AI Tag Optimization Error: ' . $e->getMessage());
            }
            // Return original tags on error
            return $tags;
        }
    }

    /**
     * Check if AI optimization is enabled
     */
    private function is_enabled(): bool
    {
        return !empty($this->options['ai_optimization_enabled']);
    }

    /**
     * Optimize tags using OpenAI GPT
     */
    private function optimize_with_openai(array $tags, string $content, string $title): array
    {
        $api_key = $this->options['ai_api_key'];
        $max_tags = $this->options['max_tags_per_post'] ?? 10;

        // Prepare the prompt
        $prompt = $this->build_optimization_prompt($tags, $content, $title, $max_tags);

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => json_encode([
                'model' => apply_filters('gd_autotag_openai_model', 'gpt-3.5-turbo'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert SEO specialist helping to optimize blog post tags. Provide only the tags as a comma-separated list, nothing else.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.3,
                'max_tokens' => 150,
            ]),
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('OpenAI API request failed: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['choices'][0]['message']['content'])) {
            throw new \Exception('Invalid OpenAI API response');
        }

        return $this->parse_ai_tags($body['choices'][0]['message']['content']);
    }

    /**
     * Optimize tags using Anthropic Claude
     */
    private function optimize_with_anthropic(array $tags, string $content, string $title): array
    {
        $api_key = $this->options['ai_api_key'];
        $max_tags = $this->options['max_tags_per_post'] ?? 10;

        $prompt = $this->build_optimization_prompt($tags, $content, $title, $max_tags);

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
            ],
            'body' => json_encode([
                'model' => apply_filters('gd_autotag_anthropic_model', 'claude-3-haiku-20240307'),
                'max_tokens' => 150,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ]),
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('Anthropic API request failed: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['content'][0]['text'])) {
            throw new \Exception('Invalid Anthropic API response');
        }

        return $this->parse_ai_tags($body['content'][0]['text']);
    }

    /**
     * Optimize tags using Google Gemini
     */
    private function optimize_with_google(array $tags, string $content, string $title): array
    {
        $api_key = $this->options['ai_api_key'];
        $max_tags = $this->options['max_tags_per_post'] ?? 10;

        $prompt = $this->build_optimization_prompt($tags, $content, $title, $max_tags);

        $model = apply_filters('gd_autotag_google_model', 'gemini-pro');
        $url = "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key={$api_key}";

        $response = wp_remote_post($url, [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.3,
                    'maxOutputTokens' => 150,
                ],
            ]),
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('Google API request failed: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception('Invalid Google API response');
        }

        return $this->parse_ai_tags($body['candidates'][0]['content']['parts'][0]['text']);
    }

    /**
     * Optimize tags using custom endpoint
     */
    private function optimize_with_custom(array $tags, string $content, string $title): array
    {
        $endpoint = apply_filters('gd_autotag_custom_ai_endpoint', '');
        
        if (empty($endpoint)) {
            throw new \Exception('Custom AI endpoint not configured');
        }

        $api_key = $this->options['ai_api_key'];
        $max_tags = $this->options['max_tags_per_post'] ?? 10;

        $prompt = $this->build_optimization_prompt($tags, $content, $title, $max_tags);

        $response = wp_remote_post($endpoint, [
            'timeout' => 30,
            'headers' => apply_filters('gd_autotag_custom_ai_headers', [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ]),
            'body' => apply_filters('gd_autotag_custom_ai_body', json_encode([
                'prompt' => $prompt,
                'tags' => $tags,
                'title' => $title,
                'content' => substr($content, 0, 500),
            ])),
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('Custom API request failed: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $tags_text = apply_filters('gd_autotag_custom_ai_parse_response', $body['tags'] ?? '', $body);

        return $this->parse_ai_tags($tags_text);
    }

    /**
     * Build optimization prompt for AI
     */
    private function build_optimization_prompt(array $tags, string $content, string $title, int $max_tags): string
    {
        $content_preview = substr(strip_tags($content), 0, 500);
        
        return sprintf(
            "Optimize and refine the following tags for a blog post. Remove duplicates, improve relevance, and ensure SEO effectiveness.\n\n" .
            "Title: %s\n\n" .
            "Content preview: %s\n\n" .
            "Current tags: %s\n\n" .
            "Instructions:\n" .
            "- Return maximum %d most relevant tags\n" .
            "- Prioritize SEO value and content relevance\n" .
            "- Remove generic or overly broad terms\n" .
            "- Consolidate similar concepts\n" .
            "- Use proper capitalization\n" .
            "- Return ONLY the tags as a comma-separated list, no explanations\n\n" .
            "Optimized tags:",
            $title,
            $content_preview,
            implode(', ', $tags),
            $max_tags
        );
    }

    /**
     * Parse AI response into tags array
     */
    private function parse_ai_tags(string $response): array
    {
        // Clean up the response
        $response = trim($response);
        $response = preg_replace('/^(tags?:|optimized tags?:)/i', '', $response);
        $response = trim($response);

        // Split by comma and clean each tag
        $tags = array_map('trim', explode(',', $response));
        
        // Filter out empty tags and limit length
        $tags = array_filter($tags, function($tag) {
            return !empty($tag) && strlen($tag) > 1 && strlen($tag) < 50;
        });

        // Capitalize first letter of each tag
        $tags = array_map('ucfirst', $tags);

        return array_values(array_unique($tags));
    }
}
