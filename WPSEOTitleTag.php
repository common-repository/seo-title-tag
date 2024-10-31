<?php

class WPSEOTitleTag
{
    private $_url;
    private $_wpOptions = array();
    private $_wpQuery;
    private $_wpDb;
    private $_isUrlTitle = false;

    public function __construct()
    {
        global $wpdb;

        $this->_wpDb = $wpdb;

        if (isset($_SERVER['REQUEST_URI'])) {
            $this->_url = $_SERVER['REQUEST_URI'];
        }

        if (!$this->_url) {
            $this->_url = $_SERVER['SCRIPT_NAME'] . $_SERVER['PATH_INFO'];
        }

        if (get_option("custom_meta_description_key")) {
            $this->_wpOptions['custom_meta_description_key'] = get_option("custom_meta_description_key");
            $this->_wpOptions['home_page_meta_description'] = get_option("home_page_meta_description");
            $this->_wpOptions['error_page_meta_description'] = get_option("error_page_meta_description");
        } else {
            $this->_wpOptions['custom_meta_description_key'] = "meta_description";
            $this->_wpOptions['home_page_meta_description'] = '';
            $this->_wpOptions['error_page_meta_description'] = '';
        }

        if (get_option("custom_title_key")) {
            $this->_wpOptions['custom_title_key'] = get_option("custom_title_key");
            $home_page_title = get_option("home_page_title");
            $this->_wpOptions['home_page_title'] = htmlspecialchars(stripslashes($home_page_title));
            $error_page_title = get_option("error_page_title");
            $this->_wpOptions['error_page_title'] = htmlspecialchars(stripslashes($error_page_title));
            $separator = get_option("separator");
            $this->_wpOptions['separator'] = htmlspecialchars(trim(stripslashes($separator)));
            $this->_wpOptions['use_category_description_as_title'] = get_option("use_category_description_as_title");
            $this->_wpOptions['include_blog_name_in_titles'] = get_option("include_blog_name_in_titles");

            $short_blog_name = get_option("short_blog_name");
            $this->_wpOptions['short_blog_name'] = htmlspecialchars(stripslashes($short_blog_name));
            $this->_wpOptions['manage_elements_per_page'] = get_option("manage_elements_per_page");
        } else {
            $this->_wpOptions['custom_title_key'] = "title_tag";
            $this->_wpOptions['home_page_title'] = '';
            $this->_wpOptions['error_page_title'] = '';
            $this->_wpOptions['separator'] = '';
            $this->_wpOptions['use_category_description_as_title'] = false;
            $this->_wpOptions['include_blog_name_in_titles'] = false;
            $this->_wpOptions['short_blog_name'] = '';
            $this->_wpOptions['manage_elements_per_page'] = 20;
        }

        if (empty($this->_wpOptions['separator'])) {
            $this->_wpOptions['separator'] = '|';
        }
    }

    public function init() {
        global $wp_query;

        $this->_wpQuery = $wp_query;
    }

    private function _getSeoTitleUrl($url)
    {
        $table_name = $this->_wpDb->prefix . "seo_title_tag_url";
        $condition = "(url LIKE '" . esc_sql($url) . "'";

        if (preg_match('/^\/(.+)$/', $url, $matches)) {
            $condition .= " OR url LIKE '" . esc_sql($matches[1]) . "'";
        }

        if (preg_match('/^\/{0,1}index.php\?{0,1}(.+)$/', $url, $matches)) {
            $condition .= " OR url LIKE '" . esc_sql($matches[1]) . "'";
        }

        $condition .= ')';

        $sql = "SELECT title from " . $table_name . " WHERE " . $condition;
        $temp = $this->_wpDb->get_row($sql);

        if (($temp != NULL) && ($temp->title != "")) {
            return $temp->title;
        } else {
            return false;
        }
    }

    public function getTitleTagReference()
    {
        return array($this, 'outputTitleTag');
    }

    public function getMetaTagReference()
    {
        return array($this, 'outputMetaTag');
    }

    private function _getHomepageTag()
    {
        if ($this->_wpOptions['home_page_title']) {
            $title = $this->_wpOptions['home_page_title'];
        } elseif ($temp = $this->_getSeoTitleUrl($this->_url)) {
            $this->_isUrlTitle = true;
            $title = $temp;
        } else {
            $title = '';
        }

        if (!empty($this->_wpOptions['home_page_title']) && !empty($this->_wpOptions['short_blog_name']) && ($this->_wpOptions['include_blog_name_in_titles'] == "1")) {
            $title = $this->_wpOptions['home_page_title'];
        } elseif (!empty($this->_wpOptions['home_page_title']) && empty($this->_wpOptions['short_blog_name']) && ($this->_wpOptions['include_blog_name_in_titles'] == "1")) {
            $title = $this->_wpOptions['home_page_title'];
        } elseif (empty($this->_wpOptions['home_page_title']) && !empty($this->_wpOptions['short_blog_name']) && ($this->_wpOptions['include_blog_name_in_titles'] == "1")) {
            $title = $this->_wpOptions['short_blog_name'];
        } elseif (empty($this->_wpOptions['home_page_title']) && ($this->_wpOptions['include_blog_name_in_titles'] == "0")) {
            //bloginfo('name');
            $title = '';
        }

        return $title;
    }

    public function outputTitleTag($title)
    {
        #remove_action('wp_title', $this->getTitleTagReference());
        $originalTitle = wp_title(' ', false);
        
        $this->init();
        // the title to print
        $title = '';

        // check if we are on the home page / Posts Page / static front page
        if (is_home()) {
            $title = $this->_getHomepageTag();
        } else {
            if (is_single() || is_page() || is_front_page()) {
                $post = null;
                $post_custom = null;

                if ($this->_wpQuery) {
                    $post = $this->_wpQuery->post;
                    $post_custom = get_post_custom($post->ID);
                }

                $custom_title_value = null;

                if (isset($post_custom[$this->_wpOptions['custom_title_key']]) && isset($post_custom[$this->_wpOptions['custom_title_key']][0])) {
                    $custom_title_value = $post_custom[$this->_wpOptions['custom_title_key']][0];
                    $custom_title_value = trim(strip_tags($custom_title_value));
                }

                if ($custom_title_value) {
                    $title = $custom_title_value;

                    if ($post->ID == get_option('page_on_front')) {
                        $this->_wpOptions['include_blog_name_in_titles'] = false;
                    }
                } elseif ($temp = $this->_getSeoTitleUrl($this->_url)) {
                    $this->_isUrlTitle = true;
                    $title = $temp;
                } else {
                    #remove_action('wp_title', $this->getTitleTagReference());
                    $title = wp_title(' ', false);

                    if (!$title || $title == ' ') {
                        $this->_wpOptions['separator'] = "";
                    }
                }
            } elseif (is_category()) {
                $category = '';
                $temp_category_title = '';

                if ($this->_wpQuery) {
                    $category = $this->_wpQuery->get_queried_object();
                }

                if ($this->_wpOptions['use_category_description_as_title'] && $category) {
                    $temp_category_title = trim(strip_tags($category->category_description));
                } else {
                    if (!empty($category->cat_ID)) {
                        $table_name = $this->_wpDb->prefix . "seo_title_tag_category";
                        $temp = $this->_wpDb->get_results("SELECT title from " . $table_name . " where category_id = " . $category->cat_ID);

                        if (is_array($temp) && isset($temp[0])) {
                            $temp_category_title = $temp[0]->title;
                        }
                    }
                }

                if ($temp_category_title) {
                    $title = $temp_category_title;
                } elseif ($temp = $this->_getSeoTitleUrl($this->_url)) {
                    $this->_isUrlTitle = true;
                    $title = $temp;
                } else {
                    $title = single_cat_title('', false);
                }
            } elseif (is_search()) {
                if ($temp = $this->_getSeoTitleUrl($this->_url)) {
                    $this->_isUrlTitle = true;
                    $title = $temp;
                } else {
                    $title = "Search results";
                    if (isset($_GET['s'])) {
                        $title .= " for " . $_GET['s'];
                    }
                }
            } elseif (function_exists('is_tag') && is_tag()) {
                $tags = explode(' ', $this->_wpQuery->query_vars['tag']);

                foreach (array_keys($tags) as $k) {
                    $sql = "SELECT
                                t.term_id
                            FROM
                                " . $this->_wpDb->terms . " t INNER JOIN " . $this->_wpDb->term_taxonomy . " tt
                                ON t.term_id = tt.term_id
                            WHERE
                                t.slug = '" . esc_sql($tags[$k]) . "' AND
                                tt.taxonomy = 'post_tag'
                            LIMIT 1";

                    $temp = $this->_wpDb->get_results($sql);

                    if (is_array($temp) && isset($temp[0])) {
                        $tags[$k] = $temp[0]->term_id;
                    } else {
                        unset($tags[$k]);
                    }
                }

                $temp_tag_title = '';

                if (is_array($tags) && 1 == count($tags)) {
                    $table_name = $this->_wpDb->prefix . "seo_title_tag_tag";
                    $temp = $this->_wpDb->get_results("SELECT title,description from " . $table_name . " where tag_id = " . $tags[0]);

                    if (is_array($temp) && isset($temp[0])) {
                        $temp_tag_title = $temp[0]->title;
                        $temp_tag_mete_decritpion = $temp[0]->description;
                    }
                }

                if (!empty($temp_tag_title) OR !empty($temp_tag_mete_decritpion)) {
                    $title = $temp_tag_title;
                    //$title.= $temp_tag_mete_decritpion;
                    //$title.= bloginfo($temp_tag_mete_decritpion);
                } elseif ($temp = $this->_getSeoTitleUrl($this->_url)) {
                    $this->_isUrlTitle = true;
                    $title = $temp;
                } else {
                    $title = single_tag_title('', false);
                }
            } elseif (is_404()) {
                if ($this->_wpOptions['error_page_title']) {
                    $title = $this->_wpOptions['error_page_title'];
                } elseif ($temp = $this->_getSeoTitleUrl($this->_url)) {
                    $this->_isUrlTitle = true;
                    $title = $temp;
                } else {
                    $title = get_bloginfo('name');
                }
            } elseif ($temp = $this->_getSeoTitleUrl($this->_url)) {
                $this->_isUrlTitle = true;
                $title = $temp;
            } elseif (is_feed()) {
                $post = $this->_wpQuery->post;
                $post_custom = get_post_custom($post->ID);
                $custom_title_value = $post_custom[$this->_wpOptions['custom_title_key']][0];
                $custom_title_value = trim(strip_tags($custom_title_value));
            } else {
                #remove_action('wp_title', $this->getTitleTagReference());
                $title = wp_title(' ', false);

                if (!title || $title == ' ') {
                    $this->_wpOptions['separator'] = "";
                }
            }

            if ($this->_wpOptions['include_blog_name_in_titles']) {
                if ($this->_wpOptions['separator']) {
                    $title .= " " . $this->_wpOptions['separator'] . " ";
                }

                if ($this->_wpOptions['short_blog_name']) {
                    $title .= $this->_wpOptions['short_blog_name'];
                }

                if (empty($this->_wpOptions['short_blog_name'])) {
                    $title .= get_bloginfo('name');
                } else {
                    $title .= '';
                }
            } elseif (empty($title)) {
                $title = get_bloginfo('name');  // this is so the page has a title, because otherwise it would have been untitled
            }
        }

        // if this is no url matched title we check if we are in paging mode and add the page number.
        if (!$this->_isUrlTitle) {
            if (preg_match('/(paged=|page=|page\/)(\d+)/', $this->_url, $matches)) {
                $title .= ' (' . $matches[2] . ')';
            }
        }

        //if (stristr($title, $originalTitle)) {
//          $title = str_replace($originalTitle, '', $title);
        //}
        
        #echo esc_html(trim($title));
        return esc_html(trim($title));
    }

    public function outputMetaTag()
    {
        $this->init();
        
        if (is_home() || is_front_page()) {
            if ($this->_wpOptions['home_page_meta_description']) {
?>
                                <meta name="description" content="<?php echo $this->_wpOptions['home_page_meta_description']; ?>" />
                <?php
            } else {
                ?>
                                <meta name="description" content="<?php echo get_bloginfo('description'); ?>" />
                <?php
            }
        } else {
            if (is_single() || is_page()) {
                $post_custom = array();

                if ($this->_wpQuery) {
                    $post = $this->_wpQuery->post;
                    $post_custom = get_post_custom($post->ID);
                }

                $custom_meta_description_key = null;

                if (isset($post_custom[$this->_wpOptions['custom_meta_description_key']]) && isset($post_custom[$this->_wpOptions['custom_meta_description_key']][0])) {
                    $custom_meta_description_key = $post_custom[$this->_wpOptions['custom_meta_description_key']][0];
                    $custom_meta_description_key = trim(strip_tags($custom_meta_description_key));
                }

                if ($custom_meta_description_key) {
                ?>
                                        <meta name="description" content="<?php echo $custom_meta_description_key; ?>" />
                    <?php
                }
            } elseif (is_category()) {
                $category = $this->_wpQuery->get_queried_object();

                $table_name = $this->_wpDb->prefix . "seo_title_tag_category";
                $temp = $this->_wpDb->get_results("SELECT title, description from " . $table_name . " where category_id = " . $category->cat_ID);
                $temp_category_meta_description = $temp[0]->description;
                    ?>
                                <meta name="description" content="<?php echo $temp_category_meta_description; ?>" />
                <?php
            } elseif (function_exists('is_tag') && is_tag()) {
                $tags = explode(' ', $this->_wpQuery->query_vars['tag']);

                foreach (array_keys($tags) as $k) {
                    $sql = "SELECT
                                t.term_id
                            FROM
                                " . $this->_wpDb->terms . " t INNER JOIN " . $this->_wpDb->term_taxonomy . " tt
                                ON t.term_id = tt.term_id
                            WHERE
                                t.slug = '" . esc_sql($tags[$k]) . "' AND
                                tt.taxonomy = 'post_tag'
                            LIMIT 1";

                    $temp = $this->_wpDb->get_results($sql);

                    if (is_array($temp) && isset($temp[0])) {
                        $tags[$k] = $temp[0]->term_id;
                    } else {
                        unset($tags[$k]);
                    }
                }

                if (is_array($tags) && 1 == count($tags)) {
                    $table_name = $this->_wpDb->prefix . "seo_title_tag_tag";
                    $temp = $this->_wpDb->get_results("SELECT title,description from " . $table_name . " where tag_id = " . $tags[0]);

                    if (is_array($temp) && isset($temp[0])) {
                        $temp_tag_mete_decritpion = $temp[0]->description;
                    }
                }

                if (!empty($temp_tag_mete_decritpion)) {
                ?>
                                        <meta name="description" content="<?php echo $temp_tag_mete_decritpion; ?>" />
                    <?php
                }
            } elseif (is_404()) {
                if ($this->_wpOptions['error_page_meta_description']) {
                    ?>
                                        <meta name="description" content="<?php echo $this->_wpOptions['error_page_meta_description']; ?>" />
                    <?php
                }
            }
        }
    }
}
