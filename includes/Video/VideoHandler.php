<?php
namespace PixelPapa\Video;

class VideoHandler {
    
    /**
     * Save generated video as new attachment
     */
    public function save_result($original_attachment_id, $video_url) {
        // Download video
        $temp_file = download_url($video_url);
        
        if (is_wp_error($temp_file)) {
            return $temp_file;
        }
        
        // Get original attachment info
        $original = get_post($original_attachment_id);
        if (!$original) {
            @unlink($temp_file);
            return new \WP_Error('attachment_not_found', __('Original attachment not found', 'pixelpapa'));
        }
        
        $filename = basename($original->guid);
        $filename_parts = pathinfo($filename);
        $new_filename = $filename_parts['filename'] . '-ai-video.mp4';
        
        // Prepare upload
        $upload_dir = wp_upload_dir();
        $new_file = $upload_dir['path'] . '/' . $new_filename;
        
        // Move file
        if (!@copy($temp_file, $new_file)) {
            @unlink($temp_file);
            return new \WP_Error('copy_failed', __('Failed to save video', 'pixelpapa'));
        }
        
        @unlink($temp_file);
        
        // Set permissions
        $stat = stat(dirname($new_file));
        $perms = $stat['mode'] & 0000666;
        @chmod($new_file, $perms);
        
        // Prepare attachment data
        $attachment = [
            'post_mime_type' => 'video/mp4',
            'post_title' => sanitize_file_name($filename_parts['filename'] . '-ai-video'),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_parent' => $original_attachment_id,
        ];
        
        // Insert attachment
        $attach_id = wp_insert_attachment($attachment, $new_file);
        
        if (is_wp_error($attach_id)) {
            @unlink($new_file);
            return $attach_id;
        }
        
        // Generate video metadata
        $attach_data = $this->generate_video_metadata($new_file);
        wp_update_attachment_metadata($attach_id, $attach_data);
        
        // Add meta
        update_post_meta($attach_id, '_pixelpapa_video', true);
        update_post_meta($attach_id, '_pixelpapa_source', $original_attachment_id);
        update_post_meta($attach_id, '_pixelpapa_video_duration', $attach_data['length'] ?? 0);
        
        return $attach_id;
    }
    
    /**
     * Generate video metadata
     */
    private function generate_video_metadata($file) {
        $metadata = [
            'file' => basename($file),
            'mime_type' => 'video/mp4',
        ];
        
        // Try to get video info if possible
        if (function_exists('wp_read_video_metadata')) {
            $video_meta = wp_read_video_metadata($file);
            if ($video_meta) {
                $metadata = array_merge($metadata, $video_meta);
            }
        }
        
        // Get file size
        $metadata['filesize'] = filesize($file);
        
        return $metadata;
    }
    
    /**
     * Get videos for an attachment
     */
    public function get_videos($attachment_id) {
        $args = [
            'post_type' => 'attachment',
            'post_parent' => $attachment_id,
            'meta_key' => '_pixelpapa_video',
            'meta_value' => true,
            'posts_per_page' => -1,
        ];
        
        $query = new \WP_Query($args);
        return $query->posts;
    }
}
