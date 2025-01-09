<?php
/**
 * Plugin Name: Custom Gallery Filenames
 * Description: Create Gallery Directory Structure Based on the Post Name. Ex wp-content/uploads/post-name/post-name-1.jpg
 * Version: 1.0
 * Author: Chris McCoy
 */

class CustomGalleryFileDirectory
{
    public function __construct()
    {
        // Disable the big image size threshold
        add_filter('big_image_size_threshold', '__return_false');

        // Sanitize the file name on upload
        add_filter('sanitize_file_name', [$this, 'sanitizeFileName'], 10, 1);

        // Handle upload prefilter
        add_filter('wp_handle_upload_prefilter', [$this, 'handleUploadPrefilter']);

        // Handle upload after the file is uploaded
        add_filter('wp_handle_upload', [$this, 'handleUpload']);
    }

    // Normalize a string for use in file names
    private function normalizeString(string $str = ''): string
    {
        $str = strip_tags($str); // Remove HTML tags
        $str = preg_replace('/[\s]+/', ' ', trim($str)); // Replace multiple spaces with a single space
        $str = preg_replace('/[\"\*\/\:\<\>\?\'\|]+/', ' ', $str); // Remove invalid characters
        $str = strtolower(html_entity_decode($str, ENT_QUOTES, "UTF-8")); // Decode HTML entities and convert to lowercase
        $str = htmlentities($str, ENT_QUOTES, "UTF-8"); // Convert special characters to HTML entities
        $str = preg_replace("/(&)([a-z])([a-z]+;)/i", '$2', $str); // Remove HTML entity references
        $str = rawurlencode(str_replace(' ', '-', $str)); // Replace spaces with hyphens and encode
        return str_replace('.jpeg', '.jpg', $str); // Change .jpeg to .jpg
    }

    // Generate a unique numbered filename based on the original filename
    private function generateNumberedFilename(string $filename): int
    {
        $hash = sha1($filename); // Create a SHA1 hash of the filename
        return hexdec(substr($hash, 0, 8)) % 100000; // Convert the first 8 characters of the hash to a number
    }

    // Sanitize the uploaded file name
    public function sanitizeFileName(string $filename): string
    {
        if (!empty($_REQUEST['post_id'])) {
            $filename = $this->normalizeString($filename); // Normalize the filename
            $post_id = (int) $_REQUEST['post_id']; // Get the post ID
            $exists = get_post_status($post_id); // Check if the post exists
            $info = pathinfo($filename); // Get file information

            // If the post exists and the file extension is valid
            if ($exists && !empty($info['extension']) && in_array(strtolower($info['extension']), ["jpg", "jpeg", "gif", "png", "bmp"])) {
                $post_object = get_post($post_id); // Get the post object
                $ext = !empty($info['extension']) ? '.' . $info['extension'] : ''; // Get the file extension
                $name = basename($filename, $ext); // Get the base name of the file
                $custom_filename = $this->generateNumberedFilename($name); // Generate a unique filename
                // Create the new filename using the post name or title
                $filename = strtolower((!empty($post_object->post_name) ? $post_object->post_name : $this->normalizeString($post_object->post_title)) . "-" . $custom_filename . $ext);
            }
        }

        return $filename; // Return the sanitized filename
    }

    // Handle the upload prefilter
    public function handleUploadPrefilter(array $file): array
    {
        if (!empty($_REQUEST['post_id'])) {
            add_filter('upload_dir', [$this, 'customUploadDir']); // Add custom upload directory filter
        }
        return $file; // Return the file array
    }

    // Handle the upload after the file is uploaded
    public function handleUpload(array $fileinfo): array
    {
        if (!empty($_REQUEST['post_id'])) {
            remove_filter('upload_dir', [$this, 'customUploadDir']); // Remove custom upload directory filter
        }
        return $fileinfo; // Return the file information
    }

    // Customize the upload directory
    public function customUploadDir(array $path): array
    {
        if (!empty($path['error'])) {
            return $path; // Return if there is an error
        }
        if (!empty($_REQUEST['post_id'])) {
            $customdir = $this->generatePath(); // Generate a custom directory path
            // Update the path and URL to use the custom directory
            $path['path'] = str_replace($path['subdir'], '', $path['path']);
            $path['url'] = str_replace($path['subdir'], '', $path['url']);
            $path['subdir'] = $customdir;
            $path['path'] .= $customdir;
            $path['url'] .= $customdir;
            return $path; // Return the modified path
        }
        return $path; // Ensure a return value
    }

    // Ensure the string has a leading slash
    private function leadingslashit(string $s): string
    {
        return $s && $s[0] !== '/' ? '/' . $s : $s; // Add leading slash if not present
    }

    // Generate a custom path based on the post name
    private function generatePath(): string
    {
        global $post;

        $post_id = intval($_REQUEST['post_id'], 10); // Get the post ID
        $post_name = 'other'; // Default post name

        // If the post is not the current post
        if (empty($post) || (!empty($post) && is_numeric($post_id) && $post_id != $post->ID)) {
            $my_post = get_post($post_id); // Get the post object
            // Get the post name or title
            $post_name = !empty($my_post->post_name) ? $my_post->post_name : (!empty($my_post->post_title) ? sanitize_title($my_post->post_title) : 'other');
        }

        return untrailingslashit($this->leadingslashit($post_name)); // Return the custom path
    }
}

// Instantiate the class to apply the functionality
new CustomGalleryFileDirectory();

