<?php
/**
 * Image Helper Functions for Compression
 */

function compressImage($source, $destination, $quality = 60) {
    if (!file_exists($source)) {
        return false;
    }

    $info = @getimagesize($source);
    
    if (!$info) {
        // Not a recognizable image, just move/copy it
        $result = @move_uploaded_file($source, $destination);
        if (!$result) $result = @copy($source, $destination);
        return $result;
    }
    
    $mime = $info['mime'];
    
    switch ($mime) {
        case 'image/jpeg':
            $image = @imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $image = @imagecreatefrompng($source);
            if ($image) {
                // Convert palette based images to true color
                if (!imageistruecolor($image)) {
                    imagepalettetotruecolor($image);
                }
                // Save alpha channel
                imagealphablending($image, false);
                imagesavealpha($image, true);
            }
            break;
        case 'image/gif':
            $image = @imagecreatefromgif($source);
            break;
        case 'image/webp':
            $image = @imagecreatefromwebp($source);
            break;
        default:
            $image = false;
            break;
    }
    
    if (!$image) {
        // Fallback to move/copy if GD fails to load it
        $result = @move_uploaded_file($source, $destination);
        if (!$result) $result = @copy($source, $destination);
        return $result;
    }

    // Resize image if it's too large (max 1200px width/height) to save more space
    $width = imagesx($image);
    $height = imagesy($image);
    $max_dim = 1200;
    
    if ($width > $max_dim || $height > $max_dim) {
        $ratio = $width / $height;
        if ($ratio > 1) {
            $new_width = $max_dim;
            $new_height = intval($max_dim / $ratio);
        } else {
            $new_height = $max_dim;
            $new_width = intval($max_dim * $ratio);
        }
        
        $new_image = imagecreatetruecolor($new_width, $new_height);
        
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
            $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
            imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
        }
        
        imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        imagedestroy($image);
        $image = $new_image;
    }
    
    $result = false;
    $ext = strtolower(pathinfo($destination, PATHINFO_EXTENSION));
    
    if ($ext === 'png') {
        // PNG compression level (0-9). Quality 60 roughly maps to level 4.
        $pngQuality = round((100 - $quality) / 10);
        $pngQuality = min(9, max(0, $pngQuality));
        $result = @imagepng($image, $destination, $pngQuality);
    } elseif ($ext === 'jpg' || $ext === 'jpeg') {
        $result = @imagejpeg($image, $destination, $quality);
    } elseif ($ext === 'webp') {
        $result = @imagewebp($image, $destination, $quality);
    } elseif ($ext === 'gif') {
        $result = @imagegif($image, $destination);
    } else {
        // Default to webp or jpeg based on support
        if (function_exists('imagewebp')) {
            $result = @imagewebp($image, $destination, $quality);
        } else {
            $result = @imagejpeg($image, $destination, $quality);
        }
    }
    
    @imagedestroy($image);
    
    // If compression failed for some reason, try to just move the uploaded file
    if (!$result) {
        $result = @move_uploaded_file($source, $destination);
        if (!$result) $result = @copy($source, $destination);
        return $result;
    }
    
    // Clean up original temp file if it was uploaded
    if (is_uploaded_file($source)) {
        @unlink($source);
    }
    
    return true;
}
