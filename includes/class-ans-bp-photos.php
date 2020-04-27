<?php

/**
 * The main class
 */
class ANS_BP_Photos {

    // Options
    const MAX_PHOTO_NUM = 10;
    const MAX_PHOTO_WIDTH = 1200;
    const MAX_PHOTO_HEIGHT = 1000;
    const MAX_THUMB_WIDTH = 140;
    const MAX_THUMB_HEIGHT = 140;

    public function __construct() {
        // Remove the 'Avatar' link and add the 'Photos' link to: Profile tabs and Admin Bar
        add_action('bp_setup_nav', [$this, 'add_profile_tabs']);
        add_action('bp_setup_nav', [$this, 'remove_profile_tabs'], 11);
        add_action('admin_bar_menu', [$this, 'edit_toolbar'], 100);

        // Form actions: uploading, deleting, etc...
        add_action('bp_actions', [$this, 'form_actions'], 9);

        // Delete photos when the user is deleted
        add_action('delete_user', [$this, 'delete_user_photos']);

        // bp-legacy template - adding photos to profile view
        add_action('bp_before_profile_loop_content', [$this, 'print_content']);
    }

    public function add_profile_tabs() {
        bp_core_new_subnav_item([
            'name' => 'Фотографии',
            'slug' => 'photos',
            'parent_slug' => 'profile',
            'parent_url' => trailingslashit(bp_displayed_user_domain() . 'profile'),
            'user_has_access' => bp_is_my_profile() || is_super_admin(),
            'position' => 30,
            'screen_function' => [$this, 'load_template'],
        ]);
    }

    public function remove_profile_tabs() {
        bp_core_remove_subnav_item('profile', 'change-avatar');
    }

    public function edit_toolbar(WP_Admin_Bar $wp_admin_bar) {
        $node = [
            'id' => 'my_page',
            'title' => 'Фотографии',
            'parent' => 'my-account-xprofile',
            'href' => bp_loggedin_user_domain() . 'profile/photos/',
        ];

        $wp_admin_bar->add_node($node);

        $wp_admin_bar->remove_node('my-account-xprofile-change-avatar');
    }

    public function user_can_edit() {
        return (bp_is_my_profile() || is_super_admin()) && bp_is_profile_component() && bp_is_current_action('photos');
    }

    public function load_template() {
        if (!$this->user_can_edit()) {
            return false;
        }

        add_action('bp_template_title', [$this, 'print_title']);
        add_action('bp_template_content', [$this, 'print_content']);

        bp_core_load_template(apply_filters('bp_core_template_plugin', 'members/single/plugins'));
    }

    public function print_title() {
        // Do not print anything
    }

    public function print_content() {
        // Define some variables that are passed to the template
        $path = '/ans-my-photos/' . bp_displayed_user_id() . '/';
        $base_url = bp_upload_dir()['baseurl'] . $path;

        $thumbs = glob(bp_upload_dir()['basedir'] . $path . '*-ansthumb.jpg');
        // Sort by date
        array_multisort(array_map('filemtime', $thumbs), SORT_NUMERIC, SORT_DESC, $thumbs);

        require_once plugin_dir_path(__DIR__) . '/templates/photos.php';
    }

    // Run method specified in form input='action', e.g. photos_upload
    public function form_actions() {
        if ($this->user_can_edit() && isset($_POST['action']) && method_exists($this, $_POST['action'])) {
            $this->{$_POST['action']}();
        }
    }

    public function get_profile_photos_url() {
        return trailingslashit(bp_displayed_user_domain() . 'profile/photos');
    }

    public function get_photo_dir($user_id) {
        return bp_upload_dir()['basedir'] . '/ans-my-photos/' . $user_id . '/';
    }

    public function get_avatar_dir($user_id) {
        return bp_upload_dir()['basedir'] . '/avatars/' . $user_id . '/';
    }

    public function photos_upload() {
        if (empty($_FILES)) {
            return;
        }

        require_once __DIR__ . '/class-ans-photo-attachment.php';

        $attachment = new ANS_Photo_Attachment();
        $files = $_FILES['photos'];

        // Loop through the submitted photos
        foreach ($files['name'] as $key => $value) {
            if ($files['name'][$key]) {
                $file = [
                    'name' => $files['name'][$key],
                    'type' => $files['type'][$key],
                    'tmp_name' => $files['tmp_name'][$key],
                    'error' => $files['error'][$key],
                    'size' => $files['size'][$key]
                ];

                $_FILES = ['photos' => $file];

                $photo = $attachment->upload($_FILES);

                if (!empty($photo['error'])) {
                    // Display the error
                    bp_core_add_message($photo['error'], 'error');
                    bp_core_redirect($this->get_profile_photos_url());
                }

                // The file was successfully uploaded!!
                $img_data = $attachment->get_image_data($photo['file']);

                // Make resized compressed jpg copies
                // Resize only if the image is bigger than our dimensions, otherwise there will be a resizing error
                // Full image
                $ansfull = $attachment->edit_image('ansfull', [
                    'file' => $photo['file'],
                    'max_w' => $img_data['width'] > ANS_BP_Photos::MAX_PHOTO_WIDTH ? ANS_BP_Photos::MAX_PHOTO_WIDTH : 0,
                    'max_h' => $img_data['height'] > ANS_BP_Photos::MAX_PHOTO_HEIGHT ? ANS_BP_Photos::MAX_PHOTO_HEIGHT : 0,
                    'quality' => 70,
                    'save' => false,
                ]);

                $this->redirect_if_error($ansfull, [$photo['file']]);

                $ansfull_saved = $ansfull->save($ansfull->generate_filename('ansfull'), 'jpg');

                $this->redirect_if_error($ansfull_saved, [$photo['file']]);

                // Thumbnail
                $ansthumb = $attachment->edit_image('ansthumb', [
                    'file' => $photo['file'],
                    'max_w' => $img_data['width'] > ANS_BP_Photos::MAX_THUMB_WIDTH ? ANS_BP_Photos::MAX_THUMB_WIDTH : 0,
                    'max_h' => $img_data['height'] > ANS_BP_Photos::MAX_THUMB_HEIGHT ? ANS_BP_Photos::MAX_THUMB_HEIGHT : 0,
                    'quality' => 70,
                    'crop' => true,
                    'save' => false,
                ]);

                $this->redirect_if_error($ansthumb, [$photo['file'], $ansfull_saved['path']]);

                $ansthumb_saved = $ansthumb->save($ansthumb->generate_filename('ansthumb'), 'jpg');

                $this->redirect_if_error($ansthumb_saved, [$photo['file'], $ansfull_saved['path']]);

                // Delete the original
                unlink($photo['file']);
            }
        }

        $this->record_profile_updated_activity();
        bp_core_redirect($this->get_profile_photos_url());
    }

    public function redirect_if_error($object, $unlink_arr = []) {
        if (is_wp_error($object)) {
            // Delete specified files
            array_map('unlink', $unlink_arr);

            bp_core_add_message($object->get_error_message(), 'error');
            bp_core_redirect($this->get_profile_photos_url());
        }
    }

    public function delete_user_photos($user_id) {
        $photo_dir = $this->get_photo_dir($user_id);

        array_map('unlink', glob("$photo_dir*"));
        array_map('rmdir', glob("$photo_dir")); // If the dir does not exist there will be no warning
    }

    public function photo_delete() {
        $ansfull = basename(filter_input(INPUT_POST, 'url', FILTER_VALIDATE_URL));

        if (!$ansfull) {
            return;
        }

        $photo_dir = $this->get_photo_dir(bp_displayed_user_id());

        unlink($photo_dir . $ansfull);
        unlink($photo_dir . str_replace('-ansfull', '-ansthumb', $ansfull));

        $this->record_profile_updated_activity();
        bp_core_redirect($this->get_profile_photos_url());
    }

    public function avatar_update() {
        $ansfull = basename(filter_input(INPUT_POST, 'url', FILTER_VALIDATE_URL));

        if (!$ansfull) {
            return;
        }

        // Copy -ansfull.jpg to /avatars/ to process, BP will delete it
        $photo_dir = $this->get_photo_dir(bp_displayed_user_id());
        $avatar_dir = $this->get_avatar_dir(bp_displayed_user_id());

        wp_mkdir_p($avatar_dir);
        copy($photo_dir . $ansfull, $avatar_dir . $ansfull);

        $crop = [
            'item_id' => bp_displayed_user_id(),
            'original_file' => $avatar_dir . $ansfull,
            'crop_x' => $_POST['x'],
            'crop_y' => $_POST['y'],
            'crop_w' => $_POST['w'],
            'crop_h' => $_POST['h'],
        ];

        if (!bp_core_avatar_handle_crop($crop)) {
            unlink($avatar_dir . $ansfull);

            bp_core_add_message('Возникла проблема при обновлении аватара', 'error');
            bp_core_redirect($this->get_profile_photos_url());
        }

        $this->record_profile_updated_activity();
        bp_core_redirect($this->get_profile_photos_url());
    }

    public function is_avatar_exists() {
        return is_dir($this->get_avatar_dir(bp_displayed_user_id()));
    }

    public function avatar_delete() {
        bp_core_delete_existing_avatar();

        $this->record_profile_updated_activity();
        bp_core_redirect($this->get_profile_photos_url());
    }

    public function record_profile_updated_activity() {
        // Trick to make BuddyPress think that the profile fields were updated with new values to generate an activity
        $field_ids = [PHP_INT_MAX];
        $old_values = [PHP_INT_MAX => ['value' => 'old', 'visibility' => 'public']];
        $new_values = [PHP_INT_MAX => ['value' => 'new', 'visibility' => 'public']];

        bp_xprofile_updated_profile_activity(bp_displayed_user_id(), $field_ids, false, $old_values, $new_values);
    }

}
