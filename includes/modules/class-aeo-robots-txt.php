<?php
/**
 * Append AI crawler rules to the WordPress-generated robots.txt.
 *
 * WordPress generates robots.txt dynamically via the `robots_txt` filter.
 * This module appends AI-specific crawler rules (allow/disallow for GPTBot,
 * ClaudeBot, PerplexityBot, Google-Extended, etc.).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AEO_Robots_Txt {

    public function __construct() {
        add_filter( 'robots_txt', array( $this, 'append_ai_rules' ), 100, 2 );
    }

    /**
     * Append AI crawler rules to robots.txt output.
     *
     * @param string $output  Current robots.txt content.
     * @param bool   $public  Whether the blog is public.
     * @return string Modified robots.txt content.
     */
    public function append_ai_rules( $output, $public ) {
        // Don't add rules if site is not public.
        if ( ! $public ) {
            return $output;
        }

        $rules = get_option( 'aeo_robots_ai_rules', array() );

        if ( empty( $rules ) || ! is_array( $rules ) ) {
            return $output;
        }

        // Add a separator comment.
        $output .= "\n# AEO Content AI Studio - AI Crawler Rules\n";

        foreach ( $rules as $rule ) {
            $output .= $rule . "\n";
        }

        return $output;
    }
}
