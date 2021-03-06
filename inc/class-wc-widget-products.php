<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * List products. One widget to rule them all.
 *
 * @author   WooThemes
 * @category Widgets
 * @package  WooCommerce/Widgets
 * @version  2.3.0
 * @extends  WC_Widget
 */


$category_options = array();

function get_cate()
{
    global $category_options;
    $categories = get_terms('product_cat');
    $cates = array('' => 'Tất cả');
    foreach ($categories as $cat) {
        $cates[$cat->slug] = $cat->name;
    }
    $category_options = array(
        'type' => 'select',
        'std' => '',
        'label' => 'Danh Mục',
        'options' => $cates,
    );
}

add_action('init', 'get_cate');


class Widget_Products_Vien_An extends WC_Widget_Vien_An
{

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->widget_cssclass = 'woocommerce widget_products';
        $this->widget_description = 'Danh sách các sản phẩm phân loại theo danh mục';
        $this->widget_id = 'products_vienan';
        $this->widget_name = 'Sản Phẩm Theo Danh Mục';

        global $cates;


        $this->settings = array(
            'title' => array(
                'type' => 'text',
                'std' => __('', 'woocommerce'),
                'label' => __('Title', 'woocommerce'),
            ),
            'number' => array(
                'type' => 'number',
                'step' => 1,
                'min' => 1,
                'max' => '',
                'std' => 5,
                'label' => __('Number of products to show', 'woocommerce'),
            ),
            'show' => array(
                'type' => 'select',
                'std' => '',
                'label' => __('Show', 'woocommerce'),
                'options' => array(
                    '' => __('All products', 'woocommerce'),
                    'featured' => __('Featured products', 'woocommerce'),
                    'onsale' => __('On-sale products', 'woocommerce'),
                ),
            ),
            'orderby' => array(
                'type' => 'select',
                'std' => 'date',
                'label' => __('Order by', 'woocommerce'),
                'options' => array(
                    'date' => __('Date', 'woocommerce'),
                    'price' => __('Price', 'woocommerce'),
                    'rand' => __('Random', 'woocommerce'),
                    'sales' => __('Sales', 'woocommerce'),
                ),
            ),
            'order' => array(
                'type' => 'select',
                'std' => 'desc',
                'label' => _x('Order', 'Sorting order', 'woocommerce'),
                'options' => array(
                    'asc' => __('ASC', 'woocommerce'),
                    'desc' => __('DESC', 'woocommerce'),
                ),
            ),
            'hide_free' => array(
                'type' => 'checkbox',
                'std' => 0,
                'label' => __('Hide free products', 'woocommerce'),
            ),
            'show_hidden' => array(
                'type' => 'checkbox',
                'std' => 0,
                'label' => __('Show hidden products', 'woocommerce'),
            ),
            'banner' => array(
                'type' => 'textarea',
                'std' => __('', 'woocommerce'),
                'label' => 'Banner'
            ),

        );

        parent::__construct();
    }

    public function form($instance)
    {
        global $category_options;
        if (!$this->settings['category']) {
            $this->settings['category'] = $category_options;
        }
        parent::form($instance); // TODO: Change the autogenerated stub
    }

    /**
     * Query the products and return them.
     * @param  array $args
     * @param  array $instance
     * @return WP_Query
     */
    public function get_products($args, $instance)
    {
        global $category_options;
        if (!$this->settings['category']) {
            $this->settings['category'] = $category_options;
        }
        $number = !empty($instance['number']) ? absint($instance['number']) : $this->settings['number']['std'];
        $show = !empty($instance['show']) ? sanitize_title($instance['show']) : $this->settings['show']['std'];
        $orderby = !empty($instance['orderby']) ? sanitize_title($instance['orderby']) : $this->settings['orderby']['std'];
        $order = !empty($instance['order']) ? sanitize_title($instance['order']) : $this->settings['order']['std'];
        $category = !empty($instance['category']) ? sanitize_title($instance['category']) : $this->settings['category']['std'];
        $product_visibility_term_ids = wc_get_product_visibility_term_ids();

        $query_args = array(
            'posts_per_page' => $number,
            'post_status' => 'publish',
            'post_type' => 'product',
            'no_found_rows' => 1,
            'order' => $order,
            'meta_query' => array(),
            'tax_query' => array(
                'relation' => 'AND',
            ),
        );

        if (empty($instance['show_hidden'])) {
            $query_args['tax_query'][] = array(
                'taxonomy' => 'product_visibility',
                'field' => 'term_taxonomy_id',
                'terms' => is_search() ? $product_visibility_term_ids['exclude-from-search'] : $product_visibility_term_ids['exclude-from-catalog'],
                'operator' => 'NOT IN',
            );
            $query_args['post_parent'] = 0;
        }

        if (!empty($instance['hide_free'])) {
            $query_args['meta_query'][] = array(
                'key' => '_price',
                'value' => 0,
                'compare' => '>',
                'type' => 'DECIMAL',
            );
        }

        if ('yes' === get_option('woocommerce_hide_out_of_stock_items')) {
            $query_args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_visibility',
                    'field' => 'term_taxonomy_id',
                    'terms' => $product_visibility_term_ids['outofstock'],
                    'operator' => 'NOT IN',
                ),
            );
        }

        switch ($show) {
            case 'featured' :
                $query_args['tax_query'][] = array(
                    'taxonomy' => 'product_visibility',
                    'field' => 'term_taxonomy_id',
                    'terms' => $product_visibility_term_ids['featured'],
                );
                break;
            case 'onsale' :
                $product_ids_on_sale = wc_get_product_ids_on_sale();
                $product_ids_on_sale[] = 0;
                $query_args['post__in'] = $product_ids_on_sale;
                break;
        }

        switch ($orderby) {
            case 'price' :
                $query_args['meta_key'] = '_price';
                $query_args['orderby'] = 'meta_value_num';
                break;
            case 'rand' :
                $query_args['orderby'] = 'rand';
                break;
            case 'sales' :
                $query_args['meta_key'] = 'total_sales';
                $query_args['orderby'] = 'meta_value_num';
                break;
            default :
                $query_args['orderby'] = 'date';
        }

        if (!empty($category)) {
            $query_args['product_cat'] = $category;
        }

        return new WP_Query(apply_filters('woocommerce_products_widget_query_args', $query_args));
    }

    /**
     * Output widget.
     *
     * @see WP_Widget
     *
     * @param array $args
     * @param array $instance
     */
    public function widget($args, $instance)
    {
        $start = $this->get_start_category_widget_html($instance['category'], $instance);
        $end = '';
        if (!empty($instance['banner'])) {
            $end .= '</div></div><div class="cell small-3 banner-right"><div class="wrapper">' . $instance['banner'] . '</div>';
        }
        $end .= '</div></section>';

        if ($this->get_cached_widget($args)) {
            return;
        }

        ob_start();

        if (($products = $this->get_products($args, $instance)) && $products->have_posts()) {
            $this->widget_start($args, $instance);

            echo apply_filters('woocommerce_before_widget_product_list', $start);

            while ($products->have_posts()) {
                $products->the_post();

                wc_get_template('template-parts/post/content-widget-product-category.php', array('show_rating' => false), get_template_directory());

//
            }

            echo apply_filters('woocommerce_after_widget_product_list', $end);

            $this->widget_end($args);
        }

        wp_reset_postdata();

        echo $this->cache_widget($args, ob_get_clean());
    }

    public function get_start_category_widget_html($slug = '', $instance)
    {

        $number = !empty($instance['number']) ? absint($instance['number']) : 6;
        $number = ($number == 4 || $number == 8) ? 4 : 6;
        $res = '<section class="row multi-post"><div class="category small-12"><div class="category-left">';

        $categories = get_all_categories($slug);
        if (count($categories) > 0) {
            $category = $categories[0];
            $sub = get_subcategories($category->term_id);
            if (empty($slug)) {
                $res .= 'Sản phẩm mới - Hàng mới về';
            } else {
                $res .= '<a href="' . esc_url(get_category_link($category->term_id)) . '">' . $category->name . '</a>';
            }
            $res .= '</div><div class="category-right">';

            foreach ($sub as $cat) {
                $res .= '<a href="' . esc_url(get_category_link($cat->term_id)) . '">' . $cat->name . '</a>';
            }
            if ($number != 6) {
                $number = 4;
            }
            $res .= ' </div></div>';
            if (!empty($instance['banner'])) {
                $res .= '<div class="small-12"><div class="grid-x align-spaced"><div class="cell small-9">';
            }
            $res .= '<div class="row small-up-2 medium-up-3 large-up-' . $number . ' small-12">';
        }
        return $res;
    }
}
