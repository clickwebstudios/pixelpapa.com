<?php
namespace PixelPapa\Media;

class ImageHandler {
    
    /**
     * Save enhanced image as new attachment
     */
    public function save_result($original_attachment_id, $image_url) {
        // Download image
        $temp_file = download_url($image_url);
        
        if (is_wp_error($temp_file)) {
            return $temp_file;
        }
        
        // Get original attachment info
        $original = get_post($original_attachment_id);
        if (!$original) {
            @unlink($temp_file);
            return new \WP_Error('attachment_not_found', __('Original attachment not found', 'pixelpapa'));
        }
        
        $original_meta = wp_get_attachment_metadata($original_attachment_id);
        $original_file = get_attached_file($original_attachment_id);
        
        // Generate filename
        $filename = basename($original->guid);
        $filename_parts = pathinfo($filename);
        $new_filename = $filename_parts['filename'] . '-enhanced.' . ($filename_parts['extension'] ?? 'jpg');
        
        // Prepare upload
        $upload_dir = wp_upload_dir();
        $new_file = $upload_dir['path'] . '/' . $new_filename;
        
        // Move file
        if (!@copy($temp_file, $new_file)) {
            @unlink($temp_file);
            return new \WP_Error('copy_failed', __('Failed to save enhanced image', 'pixelpapa'));
        }
        
        @unlink($temp_file);
        
        // Set permissions
        $stat = stat(dirname($new_file));
        $perms = $stat['mode'] & 0000666;
        @chmod($new_file, $perms);
        
        // Get file type
        $filetype = wp_check_filetype($new_filename, null);
        
        // Prepare attachment data
        $attachment = [
            'post_mime_type' => $filetype['type'],
            'post_title' => sanitize_file_name($filename_parts['filename'] . '-enhanced'),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_parent' => $original_attachment_id, // Link to original
        ];
        
        // Insert attachment
        $attach_id = wp_insert_attachment($attachment, $new_file);
        
        if (is_wp_error($attach_id)) {
            @unlink($new_file);
            return $attach_id;
        }
        
        // Generate metadata and thumbnails
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $new_file);
        wp_update_attachment_metadata($attach_id, $attach_data);
        
        // Add meta to mark as AI enhanced
        update_post_meta($attach_id, '_pixelpapa_enhanced', true);
        update_post_meta($attach_id, '_pixelpapa_source', $original_attachment_id);
        
        return $attach_id;
    }
    
    /**
     * Get enhanced versions of an attachment
     */
    public function get_enhanced_versions($attachment_id) {
        $args = [
            'post_type' => 'attachment',
            'post_parent' => $attachment_id,
            'meta_key' => '_pixelpapa_enhanced',
            'meta_value' => true,
            'posts_per_page' => -1,
        ];
        
        $query = new \WP_Query($args);
        return $query->posts;
    }
}
