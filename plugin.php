<?php
/**
 * Plugin Name: Custom Gallery Filenames
 * Description: Create Gallery Directory Structure Based on the Post Name. Ex wp-content/uploads/post-name/post-name-1.jpg
 * Version: 1.0
 * Author: Chris McCoy
 */

class Custom_Gallery_File_Directory
{
    public function __construct()
    {
        // Disable the big image size threshold
        add_filter('big_image_size_threshold', '__return_false');
        // Sanitize the file name on upload
        add_filter('sanitize_file_name', [$this, 'sanitize_file_name'], 10, 1);
        // Handle upload pre-filter
        add_filter('wp_handle_upload_prefilter', [$this, 'wp_handle_upload_prefilter']);
        // Handle upload after the file is uploaded
        add_filter('wp_handle_upload', [$this, 'wp_handle_upload']);
    }

    // Normalize the string for file naming
    private function normalize_string($str = '')
    {
        $str = strip_tags($str); // Remove HTML tags
        $str = preg_replace('/[\s]+/', ' ', trim($str)); // Replace multiple spaces with a single space
        $str = preg_replace('/[\"\*\/\:\<\>\?\'\|]+/', ' ', $str); // Remove invalid characters
        $str = strtolower(html_entity_decode($str, ENT_QUOTES, "UTF-8")); // Decode HTML entities and convert to lowercase
        $str = htmlentities($str, ENT_QUOTES, "UTF-8"); // Convert special characters to HTML entities
        $str = preg_replace("/(&)([a-z])([a-z]+;)/i", '$2', $str); // Remove HTML entity references
        $str = rawurlencode(str_replace(' ', '-', $str)); // Replace spaces with hyphens and encode
        return str_replace('.jpeg', '.jpg', $str); // Ensure .jpeg is replaced with .jpg
    }

    // Generate a unique numbered filename
    private function generate_numbered_filename($filename)
    {
        $hash = sha1($filename); // Create a SHA1 hash of the filename
        return hexdec(substr($hash, 0, 8)) % 100000; // Return a unique number based on the hash
    }

    // Sanitize the uploaded file name
    public function sanitize_file_name($filename)
    {
        if (!empty($_REQUEST['post_id'])) {
            $filename = $this->normalize_string($filename); // Normalize the filename
            $post_id = (int) $_REQUEST['post_id']; // Get the post ID
            $exists = get_post_status($post_id); // Check if the post exists
            $info = pathinfo($filename); // Get file info

            // If the post exists and the file extension is valid
            if ($exists && !empty($info['extension']) && in_array(strtolower($info['extension']), ["jpg", "jpeg", "gif", "png", "bmp"])) {
                $post_object = get_post($post_id); // Get the post object
                $ext = !empty($info['extension']) ? '.' . $info['extension'] : ''; // Get the file extension
                $name = basename($filename, $ext); // Get the base name without extension
                $custom_filename = $this->generate_numbered_filename($name); // Generate a unique filename
                // Create the new filename
                $filename = strtolower((!empty($post_object->post_name) ? $post_object->post_name : $this->normalize_string($post_object->post_title)) . "-" . $custom_filename . $ext);
            }
        }

        return $filename; // Return the sanitized filename
    }

    // Handle upload pre-filter
    public function wp_handle_upload_prefilter($file)
    {
        if (!empty($_REQUEST['post_id'])) {
            add_filter('upload_dir', [$this, 'custom_upload_dir']); // Add custom upload directory filter
        }
        return $file; // Return the file
    }

    // Handle upload after the file is uploaded
    public function wp_handle_upload($fileinfo)
    {
        if (!empty($_REQUEST['post_id'])) {
            remove_filter('upload_dir', [$this, 'custom_upload_dir']); // Remove custom upload directory filter
        }
        return $fileinfo; // Return the file info
    }

    // Define a custom upload directory
    public function custom_upload_dir($path)
    {
        if (!empty($path['error'])) {
            return $path; // Return if there's an error
        }
        if (!empty($_REQUEST['post_id'])) {
            $customdir = $this->generate_path(); // Generate the custom directory path
            // Update the path and URL
            $path['path'] = str_replace($path['subdir'], '', $path['path']);
            $path['url'] = str_replace($path['subdir'], '', $path['url']);
            $path['subdir'] = $customdir;
            $path['path'] .= $customdir;
            $path['url'] .= $customdir;
            return $path; // Return the modified path
        }
    }

    // Ensure the string starts with a leading slash
    private function leadingslashit($s)
    {
        return $s && $s[0] !== '/' ? '/' . $s : $s; // Add leading slash if not present
    }

    // Generate a path based on the post name
    private function generate_path()
    {
        global $post;

        $post_id = intval($_REQUEST['post_id'], 10); // Get the post ID
        $post_name = 'other'; // Default post name

        // If the post is not the current post
        if (empty($post) || (!empty($post) && is_numeric($post_id) && $post_id != $post->ID)) {
            $my_post = get_post($post_id); // Get the post object
            // Set the post name based on the post object
            $post_name = !empty($my_post->post_name) ? $my_post->post_name : (!empty($my_post->post_title) ? sanitize_title($my_post->post_title) : 'other');
        }

        return untrailingslashit($this->leadingslashit($post_name)); // Return the sanitized post name
    }
}

// Instantiate the class
new Custom_Gallery_File_Directory();
