<?php
/*
  Plugin Name: Simple Custom Post Order
  Plugin URI: http://hsameer.com.np/simple-custom-post-order/
  Description: Order Items (Posts, Pages, and Custom Post Types) using a Drag and Drop Sortable JavaScript.
  Version: 2.3
  Author: Sameer Humagain
  Author URI: http://hsameer.com.np/
 */

define('RS_ORDER_URL', plugins_url('', __FILE__));

$rs_order = new RS_O_Engine();
class RS_O_Engine {

    function __construct() {
        if (!get_option('rs_order_install'))
            $this->rs_order_install();
        add_action('admin_init', array($this, 'refresh'));
        add_action('admin_init', array($this, 'update_options'));
        add_action('admin_init', array($this, 'load_script_css'));

        add_action('wp_ajax_update-menu-order', array($this, 'update_menu_order'));
        add_action('wp_ajax_update-menu-order-tags', array($this, 'update_menu_order_tags'));

        add_action('pre_get_posts', array($this, 'rs_order_pre_get_posts'));

        add_filter('get_previous_post_where', array($this, 'rs_order_previous_post_where'));
        add_filter('get_previous_post_sort', array($this, 'rs_order_previous_post_sort'));
        add_filter('get_next_post_where', array($this, 'rs_order_next_post_where'));
        add_filter('get_next_post_sort', array($this, 'rs_order_next_post_sort'));

        add_filter('get_terms_orderby', array($this, 'rs_order_get_terms_orderby'), 10, 3);
        add_filter('wp_get_object_terms', array($this, 'rs_order_get_object_terms'), 10, 3);
        add_filter('get_terms', array($this, 'rs_order_get_object_terms'), 10, 3);
    }

    function rs_order_install() {
        global $wpdb;
        $result = $wpdb->query("DESCRIBE $wpdb->terms `term_order`");
        if (!$result) {
            $query = "ALTER TABLE $wpdb->terms ADD `term_order` INT( 4 ) NULL DEFAULT '0'";
            $result = $wpdb->query($query);
        }
        update_option('rs_order_install', 1);
    }

    
    function _check_load_script_css() {
        $active = false;
        $objects = array('page','post');
        $tags = $this->get_rs_order_options_tags();

        if (empty($objects) && empty($tags))
            return false;

        if (isset($_GET['orderby']) || strstr($_SERVER['REQUEST_URI'], 'action=edit') || strstr($_SERVER['REQUEST_URI'], 'wp-admin/post-new.php'))
            return false;

        if (!empty($objects)) {
            if (isset($_GET['post_type']) && !isset($_GET['taxonomy']) && in_array($_GET['post_type'], $objects)) { // if page or custom post types
                $active = true;
            }
            if (!isset($_GET['post_type']) && strstr($_SERVER['REQUEST_URI'], 'wp-admin/edit.php') && in_array('post', $objects)) { // if post
                $active = true;
            }
        }

        if (!empty($tags)) {
            if (isset($_GET['taxonomy']) && in_array($_GET['taxonomy'], $tags)) {
                $active = true;
            }
        }

        return $active;
    }

    function load_script_css() {
        if ($this->_check_load_script_css()) {
            wp_enqueue_script('jquery');
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_script('rs_orderjs', RS_DIR . '/assets/js/rs_order.js', array('jquery'), null, true);
        }
    }

    function refresh() {
        global $wpdb;
        $objects = array('page','post');
        $tags = $this->get_rs_order_options_tags();

        if (!empty($objects)) {
            foreach ($objects as $object) {
                $result = $wpdb->get_results("
					SELECT count(*) as cnt, max(menu_order) as max, min(menu_order) as min 
					FROM $wpdb->posts 
					WHERE post_type = '" . $object . "' AND post_status IN ('publish', 'pending', 'draft', 'private', 'future')
				");
                if ($result[0]->cnt == 0 || $result[0]->cnt == $result[0]->max)
                    continue;

                $results = $wpdb->get_results("
					SELECT ID 
					FROM $wpdb->posts 
					WHERE post_type = '" . $object . "' AND post_status IN ('publish', 'pending', 'draft', 'private', 'future') 
					ORDER BY menu_order ASC
				");
                foreach ($results as $key => $result) {
                    $wpdb->update($wpdb->posts, array('menu_order' => $key + 1), array('ID' => $result->ID));
                }
            }
        }

        if (!empty($tags)) {
            foreach ($tags as $taxonomy) {
                $result = $wpdb->get_results("
					SELECT count(*) as cnt, max(term_order) as max, min(term_order) as min 
					FROM $wpdb->terms AS terms 
					INNER JOIN $wpdb->term_taxonomy AS term_taxonomy ON ( terms.term_id = term_taxonomy.term_id ) 
					WHERE term_taxonomy.taxonomy = '" . $taxonomy . "'
				");
                if ($result[0]->cnt == 0 || $result[0]->cnt == $result[0]->max)
                    continue;

                $results = $wpdb->get_results("
					SELECT terms.term_id 
					FROM $wpdb->terms AS terms 
					INNER JOIN $wpdb->term_taxonomy AS term_taxonomy ON ( terms.term_id = term_taxonomy.term_id ) 
					WHERE term_taxonomy.taxonomy = '" . $taxonomy . "' 
					ORDER BY term_order ASC
				");
                foreach ($results as $key => $result) {
                    $wpdb->update($wpdb->terms, array('term_order' => $key + 1), array('term_id' => $result->term_id));
                }
            }
        }
    }

    function update_menu_order() {
        global $wpdb;

        parse_str($_POST['order'], $data);

        if (!is_array($data))
            return false;

        $id_arr = array();
        foreach ($data as $key => $values) {
            foreach ($values as $position => $id) {
                $id_arr[] = $id;
            }
        }

        $menu_order_arr = array();
        foreach ($id_arr as $key => $id) {
            $results = $wpdb->get_results("SELECT menu_order FROM $wpdb->posts WHERE ID = " . intval($id));
            foreach ($results as $result) {
                $menu_order_arr[] = $result->menu_order;
            }
        }

        sort($menu_order_arr);

        foreach ($data as $key => $values) {
            foreach ($values as $position => $id) {
                $wpdb->update($wpdb->posts, array('menu_order' => $menu_order_arr[$position]), array('ID' => intval($id)));
            }
        }
    }

    function update_menu_order_tags() {
        global $wpdb;

        parse_str($_POST['order'], $data);

        if (!is_array($data))
            return false;

        $id_arr = array();
        foreach ($data as $key => $values) {
            foreach ($values as $position => $id) {
                $id_arr[] = $id;
            }
        }

        $menu_order_arr = array();
        foreach ($id_arr as $key => $id) {
            $results = $wpdb->get_results("SELECT term_order FROM $wpdb->terms WHERE term_id = " . intval($id));
            foreach ($results as $result) {
                $menu_order_arr[] = $result->term_order;
            }
        }
        sort($menu_order_arr);

        foreach ($data as $key => $values) {
            foreach ($values as $position => $id) {
                $wpdb->update($wpdb->terms, array('term_order' => $menu_order_arr[$position]), array('term_id' => intval($id)));
            }
        }
    }

    function update_options() {
        global $wpdb;

        if (!isset($_POST['rs_order_submit']))
            return false;

        check_admin_referer('nonce_rs_order');

        $input_options = array();
        $input_options['objects'] = isset($_POST['objects']) ? $_POST['objects'] : '';
        $input_options['tags'] = isset($_POST['tags']) ? $_POST['tags'] : '';

        update_option('rs_order_options', $input_options);

        $objects = array('page','post');
        $tags = $this->get_rs_order_options_tags();

        if (!empty($objects)) {
            foreach ($objects as $object) {
                $result = $wpdb->get_results("
					SELECT count(*) as cnt, max(menu_order) as max, min(menu_order) as min 
					FROM $wpdb->posts 
					WHERE post_type = '" . $object . "' AND post_status IN ('publish', 'pending', 'draft', 'private', 'future')
				");
                if ($result[0]->cnt == 0 || $result[0]->cnt == $result[0]->max)
                    continue;

                if ($object == 'page') {
                    $results = $wpdb->get_results("
						SELECT ID 
						FROM $wpdb->posts 
						WHERE post_type = '" . $object . "' AND post_status IN ('publish', 'pending', 'draft', 'private', 'future') 
						ORDER BY post_title ASC
					");
                } else {
                    $results = $wpdb->get_results("
						SELECT ID 
						FROM $wpdb->posts 
						WHERE post_type = '" . $object . "' AND post_status IN ('publish', 'pending', 'draft', 'private', 'future') 
						ORDER BY post_date DESC
					");
                }
                foreach ($results as $key => $result) {
                    $wpdb->update($wpdb->posts, array('menu_order' => $key + 1), array('ID' => $result->ID));
                }
            }
        }

        if (!empty($tags)) {
            foreach ($tags as $taxonomy) {
                $result = $wpdb->get_results("
					SELECT count(*) as cnt, max(term_order) as max, min(term_order) as min 
					FROM $wpdb->terms AS terms 
					INNER JOIN $wpdb->term_taxonomy AS term_taxonomy ON ( terms.term_id = term_taxonomy.term_id ) 
					WHERE term_taxonomy.taxonomy = '" . $taxonomy . "'
				");
                if ($result[0]->cnt == 0 || $result[0]->cnt == $result[0]->max)
                    continue;

                $results = $wpdb->get_results("
					SELECT terms.term_id 
					FROM $wpdb->terms AS terms 
					INNER JOIN $wpdb->term_taxonomy AS term_taxonomy ON ( terms.term_id = term_taxonomy.term_id ) 
					WHERE term_taxonomy.taxonomy = '" . $taxonomy . "' 
					ORDER BY name ASC
				");
                foreach ($results as $key => $result) {
                    $wpdb->update($wpdb->terms, array('term_order' => $key + 1), array('term_id' => $result->term_id));
                }
            }
        }

        wp_redirect('admin.php?page=rs_order-settings&msg=update');
    }

    function rs_order_previous_post_where($where) {
        global $post;

        $objects = $this->get_rs_order_options_objects();
        if (empty($objects))
            return $where;

        if (isset($post->post_type) && in_array($post->post_type, $objects)) {
            $current_menu_order = $post->menu_order;
            $where = "WHERE p.menu_order > '" . $current_menu_order . "' AND p.post_type = '" . $post->post_type . "' AND p.post_status = 'publish'";
        }
        return $where;
    }

    function rs_order_previous_post_sort($orderby) {
        global $post;

        $objects = $this->get_rs_order_options_objects();
        if (empty($objects))
            return $orderby;

        if (isset($post->post_type) && in_array($post->post_type, $objects)) {
            $orderby = 'ORDER BY p.menu_order ASC LIMIT 1';
        }
        return $orderby;
    }

    function rs_order_next_post_where($where) {
        global $post;

        $objects = $this->get_rs_order_options_objects();
        if (empty($objects))
            return $where;

        if (isset($post->post_type) && in_array($post->post_type, $objects)) {
            $current_menu_order = $post->menu_order;
            $where = "WHERE p.menu_order < '" . $current_menu_order . "' AND p.post_type = '" . $post->post_type . "' AND p.post_status = 'publish'";
        }
        return $where;
    }

    function rs_order_next_post_sort($orderby) {
        global $post;

        $objects = $this->get_rs_order_options_objects();
        if (empty($objects))
            return $orderby;

        if (isset($post->post_type) && in_array($post->post_type, $objects)) {
            $orderby = 'ORDER BY p.menu_order DESC LIMIT 1';
        }
        return $orderby;
    }

    function rs_order_pre_get_posts($wp_query) {
        
        $objects = array('page','post');
        if (empty($objects))
            return false;
        if (is_admin()) {

            if (isset($wp_query->query['post_type']) && !isset($_GET['orderby'])) {
                if (in_array($wp_query->query['post_type'], $objects)) {
                    $wp_query->set('orderby', 'menu_order');
                    $wp_query->set('order', 'ASC');
                }
            }
        } else {

            $active = false;

            if (isset($wp_query->query['post_type'])) {
                if (!is_array($wp_query->query['post_type'])) {
                    if (in_array($wp_query->query['post_type'], $objects)) {
                        $active = true;
                    }
                }
            } else {
                if (in_array('post', $objects)) {
                    $active = true;
                }
            }

            if (!$active)
                return false;

            if (isset($wp_query->query['suppress_filters'])) {
                if ($wp_query->get('orderby') == 'date')
                    $wp_query->set('orderby', 'menu_order');
                if ($wp_query->get('order') == 'DESC')
                    $wp_query->set('order', 'ASC');
            } else {
                if (!$wp_query->get('orderby'))
                    $wp_query->set('orderby', 'menu_order');
                if (!$wp_query->get('order'))
                    $wp_query->set('order', 'ASC');
            }
        }
    }

    function rs_order_get_terms_orderby($orderby, $args) {
        if (is_admin())
            return $orderby;

        $tags = $this->get_rs_order_options_tags();

        if (!isset($args['taxonomy']))
            return $orderby;

        $taxonomy = $args['taxonomy'];
        if (!in_array($taxonomy, $tags))
            return $orderby;

        $orderby = 't.term_order';
        return $orderby;
    }

    function rs_order_get_object_terms($terms) {
        $tags = $this->get_rs_order_options_tags();

        if (is_admin() && isset($_GET['orderby']))
            return $terms;

        foreach ($terms as $key => $term) {
            if (is_object($term) && isset($term->taxonomy)) {
                $taxonomy = $term->taxonomy;
                if (!in_array($taxonomy, $tags))
                    return $terms;
            } else {
                return $terms;
            }
        }

        usort($terms, array($this, 'taxcmp'));
        return $terms;
    }

    function taxcmp($a, $b) {
        if ($a->term_order == $b->term_order)
            return 0;
        return ( $a->term_order < $b->term_order ) ? -1 : 1;
    }

    function get_rs_order_options_objects() {
        $rs_order_options = get_option('rs_order_options') ? get_option('rs_order_options') : array();
        $objects = isset($rs_order_options['objects']) && is_array($rs_order_options['objects']) ? $rs_order_options['objects'] : array();
        return $objects;
    }

    function get_rs_order_options_tags() {
        $rs_order_options = get_option('rs_order_options') ? get_option('rs_order_options') : array();
        $tags = isset($rs_order_options['tags']) && is_array($rs_order_options['tags']) ? $rs_order_options['tags'] : array();
        return $tags;
    }

}