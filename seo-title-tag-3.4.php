<?php

require_once('WPSEOTitleTag.php');

if (isset($_POST['download'])) {
    global $wpdb;
    $wp_postmeta = $wpdb->prefix . "postmeta";

    $csv_source_array = $wpdb->get_results($wpdb->prepare("SELECT wp_postmeta.post_id, wp_postmeta.meta_key, wp_postmeta.meta_value,wp_posts.post_name FROM wp_postmeta inner join wp_posts  on wp_postmeta.post_id = wp_posts.ID where wp_postmeta.meta_key ='meta_description' OR wp_postmeta.meta_key ='title_tag' order by wp_postmeta.post_id", array()), ARRAY_N);
    //var_dump($csv_source_array);exit;
    $csv_data = array();

    foreach ($csv_source_array as $csv_source) {
        //post_id,meta_title and meta_description values in array
        if (isset($csv_source[2]) && $csv_source[2] != '') {
            $csv_data[$csv_source[0]]['post_id'] = $csv_source[0];
            $csv_data[$csv_source[0]][$csv_source[1]] = $csv_source[2];
        }
        //array for slug
        //$csv_data[$csv_source[0]]['slug'] = "";
        if (isset($csv_source[3])) {
            $csv_data[$csv_source[0]]['slug'] = $csv_source[3];
        }
    }

    //change array keys of $csv_data
    foreach ($csv_data as $data) {
        $csv_data_array[] = $data;
    }

    $csv_file_name = 'SEO-Tag-.' . date('Ymd') . '.csv';
    $csv_header_array = array("Post Id", "Title", "Meta Description", "Slug");

    if (isset($csv_data_array)) {
        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename="' . $csv_file_name . '"');
        header('Content-Type: text/csv; charset=' . get_option('blog_charset'), true);

        $f = fopen('php://output', 'w') or show_error("Can't open php://output");
        $n = 0;

        if (isset($csv_header_array)) {
            if (!fputcsv($f, $csv_header_array, ',')) {
                echo "Can't write line $n: $line";
            }
        }

        foreach ($csv_data_array as $line) {
            $n++;

            if (isset($line['post_id'])) {
                $csv_field_array = array($line['post_id'], $line['title_tag'], $line['meta_description'], $line['slug']);

                if (!fputcsv($f, $csv_field_array, ',')) {
                    echo "Can't write line $n: $line";
                }
            }
        }

        fclose($f) or show_error("Can't close php://output");
        $csvStr = ob_get_contents();
        ob_end_clean();

        echo $csvStr;
        exit;
    }
}

if (!defined('COMMENT_TEXT')) {
    define('COMMENT_TEXT', '//commented by seo plugin:');
}

// this will create the DB table if needed.
function seo_title_tag_install()
{
    global $wpdb;
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    $charset_collate = '';

    if (version_compare(mysql_get_server_info(), '4.1.0', '>=')) {
        if (!empty($wpdb->charset)) {
            $charset_collate .= "DEFAULT CHARACTER SET $wpdb->charset";
        }

        if (!empty($wpdb->collate)) {
            $charset_collate .= " COLLATE $wpdb->collate";
        }
    }

    foreach ($wpdb->get_col("SHOW TABLES", 0) as $table) {
        $tables[$table] = $table;
    }

    $table_name = $wpdb->prefix . "seo_title_tag_url";
    // the URL table
    $sql = "CREATE TABLE $table_name (
      id bigint NOT NULL AUTO_INCREMENT  PRIMARY KEY,
      url varchar(255) NOT NULL,
      title varchar(255) NOT NULL,
      description varchar(255) NOT NULL,
      UNIQUE KEY id (id),
      PRIMARY KEY  (id)
    ) $charset_collate;";

    dbDelta($sql);

    // the category table
    $table_name = $wpdb->prefix . "seo_title_tag_category";
    $sql = "CREATE TABLE $table_name (
              id bigint NOT NULL AUTO_INCREMENT,
              category_id varchar(255) NOT NULL,
              title varchar(255) NOT NULL,
              description varchar(255) NOT NULL,
              UNIQUE KEY id (id),
              PRIMARY KEY  (id)
            ) $charset_collate;";

    dbDelta($sql);

    // the tag table
    $table_name = $wpdb->prefix . "seo_title_tag_tag";
    $sql = "CREATE TABLE $table_name (
              id bigint NOT NULL AUTO_INCREMENT,
              tag_id varchar(255) NOT NULL,
              title varchar(255) NOT NULL,
              description varchar (255) NOT NULL,
              UNIQUE KEY id (id),
              PRIMARY KEY  (id)
            ) $charset_collate;";

    dbDelta($sql);

    if (!get_option("custom_title_key")) {
        update_option('custom_title_key', "title_tag");
    }

    if (!get_option("custom_meta_description_key")) {
        update_option('custom_meta_description_key', "meta_description");
    }

    if (!get_option("use_category_description_as_title")) {
        update_option('use_category_description_as_title', false);
    }

    if (!get_option("include_title_form")) {
        update_option('include_title_form', true);
    }

    if (!get_option("include_meta_description_form")) {
        update_option('include_meta_description_form', true);
    }

    if (!get_option("include_slug_form")) {
        update_option('include_slug_form', true);
    }

    if (!get_option("uploaded_file")) {
        update_option('uploaded_file', true);
    }

    if (!get_option('include_blog_name_in_titles')) {
        update_option('include_blog_name_in_titles', false);
    }

    if (!get_option('manage_elements_per_page')) {
        update_option("manage_elements_per_page", 20);
    }
}

function seo_title_tag_get_taxonomy($taxonomy)
{
    global $wpdb, $wp_version;

    $results = $wpdb->get_results("
        SELECT
            tt.term_id,
            t.name,
            t.slug,
            tt.description,
            tt.parent,
            tt.count
        FROM
            " . $wpdb->term_taxonomy . " tt
            INNER JOIN " . $wpdb->terms . " t
            ON tt.term_id = t.term_id
        WHERE
            tt.taxonomy = '$taxonomy'
        ORDER BY
            t.name"
    );

    $terms = array();

    foreach ($results as $term) {
        $terms[$term->term_id] = $term;
    }

    return $terms;
}

$seoTitleTag = new WPSEOTitleTag();
$WPTitleReference = $seoTitleTag->getTitleTagReference();
$WPMetaReference = $seoTitleTag->getMetaTagReference();

// this is called on plugin activation.
add_action('wp_head', $WPMetaReference);
add_action('wp_title', $WPTitleReference);

add_action('activate_seo-title-tag/seo-title-tag.php', 'seo_title_tag_install');
add_action('admin_head', 'seo_admin_head');

function seo_admin_head()
{
    print '<link rel="stylesheet" type="text/css" href="' . get_option('siteurl') . '/wp-content/plugins/seo-title-tag/admin-2.5.css" />';
}

/// For RSS FEEDs
function wpbeginner_titlerss($content)
{
    global $wp_query;

    $postid = $wp_query->post->ID;
    $post_title = get_the_title($postid);

    $content = get_post_meta($postid, 'title_tag', true);

    if ($content == '') {
        $content = $post_title;
    }

    return $content;
}

add_filter('the_title_rss', 'wpbeginner_titlerss');

/// end for meta description 

function seo_title_tag_options_page()
{
    if (function_exists('add_options_page')) {
        add_options_page('SEO Title Tag', 'SEO Title Tag', 10, 'seo-title-tag', 'seo_title_tag_options_subpanel');
        add_management_page('Title Tags', 'Title Tags', 10, 'manage_seo_title_tags', 'manage_seo_title_tags');
    }
}

add_action('admin_menu', 'seo_title_tag_options_page');

function seo_title_tag_options_subpanel()
{
    global $wp_version;

    if (isset($_POST['info_update'])) {
        if (function_exists('check_admin_referer')) {
            check_admin_referer('seo-title-tag-action_options');
        }

        if ($_POST['custom_title_key'] != "") {
            update_option('custom_title_key', stripslashes(strip_tags($_POST['custom_title_key'])));
        }

        update_option('custom_meta_description_key', stripslashes(strip_tags($_POST['custom_meta_description_key'])));
        update_option('custom_title_key', stripslashes(strip_tags($_POST['custom_title_key'])));
        update_option('home_page_title', stripslashes(strip_tags($_POST['home_page_title'])));
        update_option('home_page_meta_description', stripslashes(strip_tags($_POST['home_page_meta_description'])));
        update_option('error_page_title', stripslashes(strip_tags($_POST['error_page_title'])));
        update_option('error_page_meta_description', stripslashes(strip_tags($_POST['error_page_meta_description'])));
        update_option('separator', stripslashes(strip_tags($_POST['separator'])));
        update_option('use_category_description_as_title', stripslashes(strip_tags($_POST['use_category_description_as_title'])));
        update_option('include_blog_name_in_titles', stripslashes(strip_tags($_POST['include_blog_name_in_titles'])));
        /* for title form hide/Show */
        update_option('include_title_form', stripslashes(strip_tags($_POST['include_title_form'])));
        /* end for title form hide/show */
        /* for Meta Description form hide/show */
        update_option('include_meta_description_form', stripslashes(strip_tags($_POST['include_meta_description_form'])));
        /* end for Meta Description form hide/show */
        /* for slug form hide/show */
        update_option('include_slug_form', stripslashes(strip_tags($_POST['include_slug_form'])));
        /* end for slug form hide/show */

        /* for uploadedfile */
        update_option('uploaded_file', stripslashes(strip_tags($_POST['uploaded_file'])));
        /* end for uploadedfile */

        update_option('short_blog_name', stripslashes(strip_tags($_POST['short_blog_name'])));
        update_option("manage_elements_per_page", intval($_POST['manage_elements_per_page']));

        echo '<div class="updated"><p>Options saved.</p></div>';
    }

    if (get_option("custom_title_key") OR get_option("custom_meta_description_key")) {
        // the name of the custom title 
        //and  Meta description
        $custom_title_key = get_option("custom_title_key");
        $custom_meta_description_key = get_option("custom_meta_description_key");
        $home_page_title = get_option("home_page_title");
        $home_page_meta_description = get_option("home_page_meta_description");
        $home_page_title = htmlspecialchars(stripslashes($home_page_title));
        $error_page_title = get_option("error_page_title");
        $error_page_meta_description = get_option("error_page_meta_description");
        $error_page_title = htmlspecialchars(stripslashes($error_page_title));
        $separator = get_option("separator");
        $separator = htmlspecialchars(stripslashes($separator));
        $use_category_description_as_title = get_option("use_category_description_as_title");
        $include_title_form = get_option("include_title_form");
        $include_meta_description_form = get_option("include_meta_description_form");
        $include_slug_form = get_option("include_slug_form");
        $uploaded_file = get_option("uploaded_file");

        // shall we always print out the blog name at the end of the title?
        $include_blog_name_in_titles = get_option("include_blog_name_in_titles");
        $short_blog_name = get_option("short_blog_name");
        $short_blog_name = htmlspecialchars(stripslashes($short_blog_name));

        // how many elements do we show per page in the manage page
        $manage_elements_per_page = get_option("manage_elements_per_page");
    } else {
        $custom_title_key = "title_tag";
        $use_category_description_as_title = false;
        $include_blog_name_in_titles = false;
        $manage_elements_per_page = 20;
    };

    //Funcations for csv file upload
    //Upload File
    if (isset($_POST['upload'])) {
        global $wpdb;

        $allowedExts = array("csv");
        $extension = end(explode(".", $_FILES['filename']['name']));

        if (($_FILES['filename']['type'] == "text/csv") || in_array($extension, $allowedExts)) {
            if ($_FILES['filename']['error'] > 0) {
                echo "Error: " . $_FILES['filename']['error'] . "<br />";
            } else {
                if (is_uploaded_file($_FILES['filename']['tmp_name'])) {
                    echo "<h3 style='color:green;'>" . "File " . $_FILES['filename']['name'] . " uploaded successfully." . "</h3>";
                }

                //Import uploaded file to Database
                $handle = fopen($_FILES['filename']['tmp_name'], "r");
                $i = 1;

                while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                    $num = count($data);

                    if ($i > 1) {
                        //// check value
                        $Postid = $data[0];
                        $meta_values = get_post_meta($Postid, $key, $single);
                        //echo"<pre>";print_r($meta_values);echo "</pre>";

                        if (array_key_exists('meta_description', $meta_values) && array_key_exists('title_tag', $meta_values)) {
                            echo "&nbsp;";
                        } else {
                            add_post_meta($Postid, get_option("custom_title_key"), $value);
                            add_post_meta($Postid, get_option("custom_meta_description_key"), $Value_meta);
                        }

                        //$data = str_replace('"',"",$data[0]);
                        //$data = explode(',',$data);
                        $meta_description_key = get_option("custom_meta_description_key");
                        $meta_title_key = get_option("custom_title_key");

//$import="INSERT into wp_postmeta(post_id,meta_key,meta_value) values('".addslashes($data[0])."','".addslashes($data[1])."','".addslashes($data[2])."')";
// $import="INSERT into wp_postmeta(post_id,meta_key,meta_value) values('".addslashes($data[0])."','".addslashes($data[1])."','".addslashes($data[2])."')";
                        $import_title = "UPDATE " . $wpdb->prefix . "postmeta SET meta_value = '" . addslashes($data[1]) . "' WHERE post_id = '" . addslashes($data[0]) . "' and meta_key = '" . $meta_title_key . "'";
                        $import_meta_description = "UPDATE " . $wpdb->prefix . "postmeta SET meta_value = '" . addslashes($data[2]) . "' WHERE post_id = '" . addslashes($data[0]) . "' and meta_key = '" . $meta_description_key . "'";
                        $import_post_slug = "UPDATE " . $wpdb->prefix . "posts SET post_name = '" . addslashes($data[3]) . "' WHERE id = '" . addslashes($data[0]) . "'";
                        mysql_query($import_title) or die(mysql_error());
                        mysql_query($import_meta_description) or die(mysql_error());
                        mysql_query($import_post_slug) or die(mysql_error());
                    } else {
                        echo $sucess_msg = "<h3 style='color:green;'>CSV File Data Successfully Inserted.</h3>";
                    }

                    $i++;
                }

                fclose($handle);
            }
        } else {
            echo "<h2 style='color:red';>Invalid File! Please Select Valid CSV File to upload</h2>";
        }
    }
    ?>

    <div class="wrap">
        <h2>SEO Title Tag Options</h2>
        <div style="width:400px;float:right">
            <div align="right" style="float:left;width:100%;">
                <div style="float: left;">

                    <a href="http://scienceofseo.com/"><img width="233" height="60" alt="visit The Science of SEO" align="right" src="<?php echo get_option('siteurl'); ?>/wp-content/plugins/seo-title-tag/nclogo.jpg" /></a>
                </div>
    <?php $url = plugins_url(); ?>
                <script type="text/javascript" src=" <?php echo $url . '/seo-title-tag/jquery-1.6.2.min.js' ?>"></script>
                <script type="text/javascript" src=" <?php echo $url . '/seo-title-tag/jquery.js' ?>"></script>
                <script type="text/javascript" src=" <?php echo $url . '/seo-title-tag/jquery.validate.min.js' ?>"></script>
                <script type="text/javascript" src=" <?php echo $url . '/seo-title-tag/testimonial.js' ?>"></script>



                <script type="text/javascript" src=" <?php echo $url . '/seo-title-tag/jquery.form.js' ?>"></script>
                <link rel="stylesheet" type="text/css" href=" <?php echo $url . '/seo-title-tag/style.css' ?>" />


                <script type='text/javascript'>
                    $(function() {
                        $('#myselect').change(function() {

                            var x = $(this).val();
                            var value = x.split("~");
                            var name = value[0];
                            var paypalemail = value[1];

                            // and update the hidden input's value
                            var str = $('#item_name').val(name);
                            var str2 = $('#business').val(paypalemail);
                        });
                    });
                </script>

                <select id='myselect'>
                    <option value='impact network~donate@impactnetwork.org'>Impact Network</option>
                    <option value='PETA~PayPal@peta.org'>PETA</option>

                </select>

                <form action="https://www.paypal.com/cgi-bin"  method="post" >
                    <input type="hidden" name="cmd" value="_donations">
                    <input type="hidden" name="business" id="business" value="donate@impactnetwork.org">
                    <input type="hidden" name="lc" value="US">

                    <input type="hidden" name="item_name" id="item_name" value="impact network" >
                    <input type="hidden" name="item_number" value="1">
                    <input type="hidden" name="no_note" value="0">
                    <input type="hidden" name="currency_code" value="USD">
                    <input type="hidden" name="bn" value="PP-DonationsBF:btn_donateCC_LG.gif:NonHostedGuest">
                    <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
                    <img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
                </form>

            </div>
            <!-- Testimonial form -->
            <div style="float:left;width:100%;">

    <?php
    $emailUser = "mjbierbach@gmail.com";
    $message = '';

    if ($_POST['testimonial'] = "testimonial") {
        $name = null;
        $email = null;
        $comments = null;

        if (isset($_POST['contactName'])) {
            $name = trim($_POST['contactName']);
        }

        if (isset($_POST['email'])) {
            $email = trim($_POST['']);
        }

        if (isset($_POST['commentsText'])) {
            $comments = trim($_POST['commentsText']);
        }

        $emailTo = $emailUser;

        //$subject = '[PHP Snippets] From '.$name;
        $subject = "Testmonial by User";
        $body = "Name: $name \n\nEmail: $email \n\nComments: $comments";
        $headers = 'From: ' . $name . ' <' . $emailTo . '>' . "\r\n" . 'Reply-To: ' . $email;
        $sent = wp_mail($emailTo, $subject, $body, $headers);
        
        if ($email) {
            $message = "Your Email Has been sent successfully.";
        }
    }
    ?>
                <div><?php echo $message; ?></div>
                <fieldset class="testimonial">
                    <legend>Testimonial</legend>
                    <form action="" id="contactForm" name="contactForm" method="post">
                        <ul class="form_inputfield">
                            <li>
                                <label for="contactName">Name*</label>
                                <input type="text" name="contactName" id="contactName" value="" />
                            </li>
                            <li>
                                <label for="email">Email*</label>
                                <input type="text" name="email" id="email" value="" />
                            </li>
                            <li>
                                <label for="commentsText">Message*</label>
                                <textarea name="commentsText" id="commentsText" rows="10" cols="30"></textarea>
                            </li>
                            <li>
                                <input type="submit" name="submit" value="submit testimonial" />
                                <input type="hidden" name="testimonial" id="testimonial" value="testimonial">
                            </li>
                            <!-- <li> Thanks for useing our plugin.. <a href="http://wordpress.org/extend/plugins/seo-title-tag/" title="Seo Title Tag" >SEO TITLE TAG</a></li> -->
                            <li>Thanks for using our <a href="http://wordpress.org/extend/plugins/seo-title-tag/" title="Seo Title Tag" target="_blank">SEO Title Tag</a> plugin.</li>
                        </ul>

                    </form></fieldset>
            </div></div>
        <form name="stto_main" method="post">
                <?php
                if (function_exists('wp_nonce_field')) {
                    wp_nonce_field('seo-title-tag-action_options');
                }
                ?>
            <table class="form-table" style="display:inline-block;width:auto;">
                <tr valign="top">
                    <th scope="row">Key name for custom Title Tag field</th>
                    <td><input name="custom_title_key" type="text" id="custom_title_key"  value="<?php echo $custom_title_key; ?>" size="40" /></td>
                </tr>
                <!-- meta description -->
                <tr valign="top">
                    <th scope="row">Key name for custom Meta Description field</th>
                    <td><input name="custom_meta_description_key" type="text" id="custom_meta_description_key" value="<?php echo $custom_meta_description_key; ?>" size="40" /></td>
                </tr>
                <!-- end meta description -->

                <tr valign="top">
                    <th scope="row">Number of posts per page in mass edit mode</th>
                    <td><input name="manage_elements_per_page" value="<?php echo $manage_elements_per_page; ?>" size="5" class="code" /></td>
                </tr>

                <tr valign="top">
    <?php if ('page' == get_option('show_on_front')) { ?>
                        <th scope="row"><a href="<?php echo get_permalink(get_option('page_for_posts')); ?>">Posts Page</a> title tag (leave blank to use blog name)</th>
    <?php } else { ?>
                        <th scope="row">Home page title tag (leave blank to use blog name)</th>
    <?php } ?>
                    <td><input name="home_page_title" value="<?php echo $home_page_title; ?>" size="60" class="code" /></td>
                </tr>
                <!--  meta description for homepage-->
                <tr valign="top">
    <?php if ('page' == get_option('show_on_front')) { ?>
                        <th scope="row"><a href="<?php echo get_permalink(get_option('page_for_posts')); ?>">Posts Page</a> title tag (leave blank to use blog name)</th>
    <?php } else { ?>
                        <th scope="row">Home page Meta Description tag (leave blank to use Default	)</th>
    <?php } ?>
                    <td><input name="home_page_meta_description" value="<?php echo $home_page_meta_description; ?>" size="60" class="code" /></td>
                </tr>

                <!-- end meta description for homepage -->
                <tr valign="top">
                    <th scope="row">404 Error title tag (leave blank to use blog name)</th>
                    <td><input name="error_page_title" value="<?php echo $error_page_title; ?>" size="60" class="code" /></td>
                </tr>
                <!--  meta description for 404 page -->
                <tr valign="top">
                    <th scope="row">404 Error Meta Description tag (leave blank to use default Meta Description)</th>
                    <td><input name="error_page_meta_description" value="<?php echo $error_page_meta_description; ?>" size="60" class="code" /></td>
                </tr>
                <!-- end meta description 404 page -->

                <tr valign="top">
                    <th scope="row">Use category descriptions as titles on category pages</th>
                    <td>
                        <label><input name="use_category_description_as_title" type="radio" value="1" <?php if ($use_category_description_as_title) {
        echo 'checked="checked"';
    } ?> /> Yes</label><br />
                        <label><input name="use_category_description_as_title"  type="radio" value="0"  <?php if (!$use_category_description_as_title) {
        echo 'checked="checked"';
    } ?> /> No</label>

                    </td>
                </tr>
                <!-- title hide/show on Post/page editing screen -->
                <tr valign="top">
                    <th scope="row">Include Title Form on Posts/Pages editing screen</th>
                    <td>
                        <label><input name="include_title_form" type="radio" value="1" <?php if ($include_title_form) {
        echo 'checked="checked"';
    } ?> /> Yes</label><br />
                        <label><input name="include_title_form"  type="radio" value="0" <?php if (!$include_title_form) {
                    echo 'checked="checked"';
                } ?> /> No</label>

                    </td>
                </tr>
                <!-- End title hide/show Post/page on editing screen -->

                <!-- Meta Description hide/show on Post/page editing screen -->
                <tr valign="top">
                    <th scope="row">Include Meta Description Form on Posts/Pages editing Screen</th>
                    <td>
                        <label><input name="include_meta_description_form" type="radio" value="1" <?php if ($include_meta_description_form) {
                    echo 'checked="checked"';
                } ?> /> Yes</label><br />
                        <label><input name="include_meta_description_form"  type="radio" value="0" <?php if (!$include_meta_description_form) {
                    echo 'checked="checked"';
                } ?> /> No</label>

                    </td>
                </tr>
                <!-- End Meta Description hide/show Post/page on editing screen -->

                <!-- Slug hide/show on Post/page editing screen -->
                <tr valign="top">
                    <th scope="row">Include Slug Form on Posts/Pages editing Screen</th>
                    <td>
                        <label><input name="include_slug_form" type="radio" value="1" <?php if ($include_slug_form) {
                    echo 'checked="checked"';
                } ?> /> Yes</label><br />
                        <label><input name="include_slug_form"  type="radio" value="0" <?php if (!$include_slug_form) {
                    echo 'checked="checked"';
                } ?> /> No</label>

                    </td>
                </tr>
                <!-- Slug hide/show Post/page on editing screen -->
                <tr valign="top">
                    <th scope="row">Include blog name in titles</th>
                    <td>
                        <label><input name="include_blog_name_in_titles" type="radio" value="1" <?php if ($include_blog_name_in_titles) {
                    echo 'checked="checked"';
                } ?> /> Yes</label><br />
                        <label><input name="include_blog_name_in_titles"  type="radio" value="0"  <?php if (!$include_blog_name_in_titles) {
                    echo 'checked="checked"';
                } ?> /> No</label>
                    </td>
                </tr>
            </table>

            <h3>Complete the following if "Yes" selected above:</h3>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Separator (leave blank to use "|")</th>
                    <td><input name="separator" value="<?php echo $separator; ?>" size="10" class="code" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Short blog name (leave blank to use full blog name)</th>
                    <td><input name="short_blog_name" value="<?php echo $short_blog_name; ?>" size="60" class="code" /></td>
                </tr>
            </table>

            <!-- </div>
             <div id='preview'>-->
            <!-- end for csv file upload -->

            <p class="submit">
                <input type="hidden" name="action" value="update" />
                <input type="hidden" name="page_options" value="custom_title_key, home_page_title,separator,use_category_description_as_title,include_blog_name_in_titles,short_blog_name"/>
                <input type="submit" name="info_update" class="button" value="<?php _e('Save Changes', 'Localization name') ?> &raquo;" />
            </p>

        </form>
        <!-- for csv file upload -->
        <h3>Upload Titles, Meta Descriptions, and Slugs directly through a CSV File:</h3>
        <form enctype='multipart/form-data' action='<?php echo $_SERVER["REQUEST_URI"]; ?>'  method='post'>
            <form enctype='multipart/form-data' action='<?php echo $_SERVER["REQUEST_URI"]; ?>'  method='post'>
                <table border="0" cellpadding="0" cellspacing="0" style="padding-left:10px;" >
                    <tr>
                        <td scope="row" width="221">Download current CSV file for editing:</td>
                        <td>
                            <input type='submit' name='download' value='Download'>
                        </td>
                    </tr>

                    <tr>
                        <td scope="row">Select a CSV file to upload:</td>
                        <td>
                            <input size='30' type='file' name='filename'>
                            <input type='submit' name='upload' value='Upload'>
                        </td>
                    </tr>
                </table>


            </form>
            <!-- end for csv file upload -->

    </div>
    <?php
}

function seo_edit_page_form()
{
    global $post;

    $custom_title_value = get_post_meta($post->ID, get_option("custom_title_key"), true);
    $custom_meta_description = get_post_meta($post->ID, get_option("custom_meta_description_key"), true);
    $include_title_form = get_option("include_title_form");
    $include_meta_description_form = get_option("include_meta_description_form");
    $include_slug_form = get_option("include_slug_form");
    ?>
    <?php $url = plugins_url(); ?>
    <script type="text/javascript">
        jQuery(document).ready(function() {
            var jdivHTML = jQuery('#seotitlejdiv').html();
            if (jQuery('#normal-sortables').length)
            {
                jQuery('#normal-sortables').prepend(jdivHTML);
            }
            jQuery('#seotitlejdiv').remove();
        });
    </script>

    <script type="text/javascript" src="<?php echo $url . '/seo-title-tag/charCount.js' ?>"></script>
    <script type="text/javascript" src="<?php echo $url . '/seo-title-tag/metaCount.js' ?>"></script>
    <script type="text/javascript">
        jQuery.noConflict();
        jQuery(document).ready(function() {
            //custom usage
            jQuery("#<?php echo get_option("custom_title_key") ?>").charCount({
                allowed: 0,
                warning: 0,
                counterText: 'Title Tag Character Count: '
            });
            //custom usage
            jQuery("#<?php echo get_option("custom_meta_description_key") ?>").metaCount({
                allowed: 0,
                warning: 0,
                counterText: 'Meta Description Character Count: '
            });
        });
    </script>

    <style>

        form div{position:relative;}
        form .counter{
            position:absolute;
            right:9px;
            top:0;
            font-size:15px;
            font-weight:bold;
            color:#FFA500;
        }
        form .warning{color:#600;}
        form .exceeded{color:#e00;}
        form .yellow1{color:#FFC40F;}
        form .green{color:green;}
        form .red{color:red;}
    </style>
    <form id="form" method="Post"> <div id="seotitlejdiv">
    <?php
    //echo "Radio buttion value here".$include_title_form;
    if ($include_title_form == 1) {
        ?>
                <div id="seodiv-title" class="postbox">
                    <h3>Title Tag (optional) - Enter your post/page title</h3>
                    <div class="inside">
                        <input type="text" name="<?php echo get_option("custom_title_key") ?>" value="<?php if ($custom_title_value) {
            echo esc_html($custom_title_value);
        } else {
            echo "";
        } ?>" id="<?php echo get_option("custom_title_key") ?>" size="50" />

                    </div>
                </div>
    <?php } else {
        echo "&nbsp;";
    } ?>
    <?php if ($include_meta_description_form == 1) { ?>

                <div id="seodiv-meta" class="postbox">
                    <h3>Meta Description (optional) - Enter your post/page meta description</h3>
                    <div class="inside">
                        <input type="text" name="<?php echo get_option("custom_meta_description_key") ?>" value="<?php if ($custom_meta_description) {
            echo esc_html($custom_meta_description);
        } else {
            echo "";
        } ?>" id="<?php echo get_option("custom_meta_description_key") ?>" size="50" />
                    </div>
                </div>
            <?php } else {
                echo "&nbsp;";
            } ?>

    <?php if (false && $include_slug_form == 1) { // disabling this as it causes issues with the other post_name text field, which exists per default ?>
        
                <div id="seodiv-slug" style="display:none;visible:hidden" class="postbox">
                    <h3>Slug </h3>
                    <div class="inside">
                        <label class="screen-reader-text" for="post_name"><?php _e('Slug') ?></label><input name="post_name" type="text" size="50" id="post_name" value="<?php echo esc_attr(apply_filters('editable_slug', $post->post_name)); ?>" />
                    </div>
                </div>
            <?php } else {
                echo "&nbsp;";
            } ?>    </div>
            <?php
        }

        function seo_update_title_tag($id)
        {
            if (isset($_POST[get_option("custom_title_key")])) {
                delete_post_meta($id, get_option("custom_title_key"));
            }

            if (isset($_POST[get_option("custom_meta_description_key")])) {
                delete_post_meta($id, get_option("custom_meta_description_key"));
            }

            $value = $_POST[get_option("custom_title_key")];
            $value = stripslashes(strip_tags($value));
            $Value_meta = $_POST[get_option("custom_meta_description_key")];
            $Value_meta = stripslashes(strip_tags($Value_meta));

            if (!empty($value)) {
                add_post_meta($id, get_option("custom_title_key"), $value);
            }

            
            if (!empty($Value_meta)) {
                add_post_meta($id, get_option("custom_meta_description_key"), $Value_meta);
            }
        }

        add_action('edit_post', 'seo_update_title_tag');
        add_action('save_post', 'seo_update_title_tag');
        add_action('publish_post', 'seo_update_title_tag');
        add_action('edit_page_form', 'seo_edit_page_form');
        add_action('edit_form_advanced', 'seo_edit_page_form');
        add_action('simple_edit_form', 'seo_edit_page_form');

// This fixes how wordpress 2.3 only shows the first tag name when you view
// Taxonomy Intersections and Unions
        function seo_title_tag_filter_single_tag_title($prefix = '', $display = true)
        {
            global $wp_query, $wpdb;

            $tags = explode(' ', str_replace(',', ' ,', $wp_query->query_vars['tag']));
            $tag_title = '';

            foreach (array_keys($tags) as $k) {
                if (0 == $k) {
                    $prefix = '';
                } elseif (',' == $tags[$k][0]) {
                    $prefix = ' or ';
                    $tags[$k] = substr($tags[$k], 1);
                } else {
                    $prefix = ' and ';
                }

                $sql = "SELECT
                    t.name
                FROM
                    " . $wpdb->terms . " t INNER JOIN " . $wpdb->term_taxonomy . " tt
                    ON t.term_id = tt.term_id
                WHERE
                    t.slug = '" . $wpdb->escape($tags[$k]) . "' AND
                    tt.taxonomy = 'post_tag'
                LIMIT 1";

                $temp = $wpdb->get_results($sql);

                if (is_array($temp) && isset($temp[0])) {
                    $tag_title .= $prefix . $temp[0]->name;
                }
            }

            return $tag_title;
        }

        add_filter('single_tag_title', 'seo_title_tag_filter_single_tag_title', 1, 2);

        function manage_seo_title_tags()
        {
            global $wpdb, $tabletags, $tablepost2tag, $install_directory, $wp_version;

            $search_value = '';
            $search_query_string = '';

            // Save Pages Form
            if (isset($_POST['action']) && (($_POST['action'] == 'pages') || ($_POST['action'] == 'posts'))) {
                if (function_exists('check_admin_referer')) {
                    check_admin_referer('seo-title-tag-action_posts-form');
                }

                foreach ($_POST as $name => $value) {

                    // Update Title Tag
                    if (preg_match('/^tagtitle_(\d+)$/', $name, $matches)) {
                        $value = stripslashes(strip_tags($value));
                        //print_r( $value); die();
                        delete_post_meta($matches[1], get_option("custom_title_key"));
                        add_post_meta($matches[1], get_option("custom_title_key"), $value);
                    }
                    // Update Descrption
                    if (preg_match('/^tagdescription_(\d+)$/', $name, $matches)) {
                        $value_meta = stripslashes(strip_tags($value));

                        delete_post_meta($matches[1], get_option("custom_meta_description_key"));
                        add_post_meta($matches[1], get_option("custom_meta_description_key"), $value_meta);
                    }

                    // Update Slug
                    if (preg_match('/^post_name_(\d+)$/', $name, $matches)) {
                        $postarr = get_post($matches[1], ARRAY_A);
                        $old_post_name = $postarr['post_name'];
                        $postarr['post_name'] = sanitize_title($value, $old_post_name);
                        $postarr['post_category'] = array();
                        $cats = get_the_category($postarr['ID']);

                        if (is_array($cats)) {
                            foreach ($cats as $cat) {
                                $postarr['post_category'][] = $cat->term_id;
                            }
                        }

                        $tags_input = array();
                        $tags = get_the_tags($postarr['ID']);

                        if (is_array($tags)) {
                            foreach ($tags as $tag) {
                                $tags_input[] = $tag->name;
                            }
                        }

                        $postarr['tags_input'] = implode(', ', $tags_input);
                        wp_insert_post($postarr);
                    }
                }

                echo '<div class="updated"><p>The custom ' . ('pages' == $_POST['action'] ? 'page' : 'post') . ' titles have been updated.</p></div>';

                // Save Category and Tag Forms
            } elseif (isset($_POST['action']) && (($_POST['action'] == 'categories') || ($_POST['action'] == 'tags'))) {
                if (function_exists('check_admin_referer')) {
                    check_admin_referer('seo-title-tag-action_taxonomy-form');
                }

                $singular = ('tags' == $_POST['action'] ? 'tag' : 'category');

                foreach ($_POST as $name => $value) {

                    // Update Title Tag
                    if (preg_match('/^title_(\d+)$/', $name, $matches)) {
                        $title = stripslashes(strip_tags($_POST['title_' . $matches[1]]));
                        $title = $wpdb->escape($title);
                        //for description
                        $tag_meta_description = stripslashes(strip_tags($_POST['description_' . $matches[1]]));
                        $tag_meta_description = $wpdb->escape($tag_meta_description);
                        /*
                          if (get_option("use_category_description_as_title")) {
                          $temp = $wpdb->get_row('SELECT term_id FROM ' . $wpdb->term_taxonomy . ' where term_id = ' . $matches[1]);
                          if ($temp->term_id == $matches[1]) {

                          $wpdb->query('UPDATE ' . $wpdb->term_taxonomy . ' SET description = \'' . $title . '\' where term_id = ' . $matches[1]);

                          }
                          } else { */

                        $table_name = $wpdb->prefix . 'seo_title_tag_' . $singular;
                        $temp = $wpdb->get_results('SELECT ' . $singular . '_id as term_id from ' . $table_name . ' WHERE ' . $singular . '_id = ' . $matches[1]);

                        if (isset($temp[1])) {
                            $wpdb->query('DELETE FROM ' . $table_name . ' WHERE ' . $singular . '_id = ' . $matches[1]);
                            unset($temp);
                        } elseif (isset($temp[0])) {
                            $temp = $temp[0];
                        }

                        if ((isset($temp)) && ($temp->term_id == $matches[1]) && (!empty($title) )) {
                            $wpdb->query('UPDATE ' . $table_name . ' SET title = \'' . $title . '\', description= \'' . $tag_meta_description . '\' WHERE ' . $singular . '_id = ' . $matches[1]);
                        } elseif (!empty($title)) {
                            $wpdb->query('INSERT INTO ' . $table_name . ' (' . $singular . '_id,title,description) values(\'' . $matches[1] . '\',\'' . $title . '\',\'' . $tag_meta_description . '\')');
                            //echo $wpdb->query('INSERT INTO ' . $table_name . ' (' . $singular . '_id,title) values(\'' . $matches[1] . '\',\'' . $title . '\')');
                        } else {
                            $wpdb->query('DELETE FROM ' . $table_name . ' where ' . $singular . '_id = ' . $matches[1]);
                        }
                        //  }
                    }
                }

                echo '<div class="updated"><p>The custom ' . $singular . ' titles have been saved.</p></div>';

                // Save URLs Form
            } elseif (isset($_POST['action']) and ($_POST['action'] == 'urls')) {
                if (function_exists('check_admin_referer')) {
                    check_admin_referer('seo-title-tag-action_urls-form');
                }

                $table_name = $wpdb->prefix . "seo_title_tag_url";

                foreach ($_POST as $name => $value) {
                    // Update Title Tag
                    if (preg_match('/^url_(\d+)$/', $name, $matches)) {
                        $url = stripslashes($value);
                        $url = $wpdb->escape($url);

                        $title = stripslashes(strip_tags($_POST['title_' . $matches[1]]));
                        $title = $wpdb->escape($title);

                        $meta_description_url = stripslashes(strip_tags($_POST['meta_description_' . $matches[1]]));
                        $meta_description_url = $wpdb->escape($meta_description_url);
                        //for url description insert
                        if ((!empty($url)) and (!empty($title))) {
                            $wpdb->query('UPDATE ' . $table_name . ' SET url = \'' . $url . '\', title = \'' . $title . '\', description = \'' . $meta_description_url . '\' WHERE id = ' . $matches[1]);
                        } elseif (empty($url) and empty($title)) {
                            $wpdb->query('DELETE FROM ' . $table_name . ' WHERE id = ' . $matches[1]);
                        }
                    } elseif (preg_match('/^url_new_(\d+)$/', $name, $matches)) {
                        $url = stripslashes($value);
                        $url = $wpdb->escape($url);

                        $title = stripslashes(strip_tags($_POST['title_new_' . $matches[1]]));
                        $title = $wpdb->escape($title);
                        //for url description insert
                        $meta_description_url = stripslashes(strip_tags($_POST['meta_description_new_' . $matches[1]]));
                        $meta_description_url = $wpdb->escape($meta_description_url);

                        if ((!empty($url)) and (!empty($title))) {
                            $wpdb->query('INSERT INTO ' . $table_name . ' (url,title,description) VALUES (\'' . $url . '\',\'' . $title . '\',\'' . $meta_description_url . '\')');
                        }
                    }
                }

                echo '<div class="updated"><p>The custom URLs and URL titles and description have been saved.</p></div>';

                // Filter by Search Value
            } elseif (isset($_POST['search_value'])) {
                $search_value = stripslashes(strip_tags($_POST['search_value']));
            }

            // If no search value from POST check for value in GET
            if (!isset($_POST['search_value']) && isset($_GET['search_value'])) {
                $search_value = stripslashes(strip_tags($_GET['search_value']));
            }

            $title_tags_type = stripslashes(strip_tags($_GET['title_tags_type']));
            $page_no = intval($_GET['page_no']);
            $manage_elements_per_page = get_option("manage_elements_per_page");
            $element_count = 0;

            if (empty($title_tags_type)) {
                $title_tags_type = 'pages';
            }

            if (empty($manage_elements_per_page)) {
                $manage_elements_per_page = 15;
            }

            $_SERVER['QUERY_STRING'] = preg_replace('/&title_tags_type=[^&]+/', '', $_SERVER['QUERY_STRING']);
            $_SERVER['QUERY_STRING'] = preg_replace('/&page_no=[^&]+/', '', $_SERVER['QUERY_STRING']);
            $_SERVER['QUERY_STRING'] = preg_replace('/&search_value=[^&]*/', '', $_SERVER['QUERY_STRING']);
            $search_query_string = '&search_value=' . $search_value;

            if (!$page_no) {
                $page_no = 0;
            }
            ?>

        <div class="wrap">

            <form  id="posts-filter" action="" method="post">
                <h2>SEO Title Tags</h2>

                <p id="post-search">
                    <label class="hidden" for="search_value">Search Title Tags:</label>
                    <input type="text" id="search_value" name="search_value" value="<?php if (isset($search_value)) {
        echo esc_html($search_value);
    } ?>" />
                    <input type="submit" value="Search Title Tags" class="button" />

                </p>

                <p><a href="options-general.php?page=seo-title-tag">Edit main SEO Title Tag plugin options &raquo;</a></p>

                <br class="clear" />

            </form>
            <!-- csv upload in tool page -->
    <?php
    //Upload File
    if (isset($_POST['upload'])) {
        global $wpdb;

        $allowedExts = array("csv");
        $extension = end(explode(".", $_FILES['filename']['name']));
        //echo "type=". $_FILES['filename']['type'];

        if (($_FILES['filename']['type'] == "text/csv") || in_array($extension, $allowedExts)) {
            if ($_FILES['filename']['error'] > 0) {
                echo "Error: " . $_FILES['filename']['error'] . "<br />";
            } else {
                if (is_uploaded_file($_FILES['filename']['tmp_name'])) {
                    echo "<h3 style='color:green;'>" . "File " . $_FILES['filename']['name'] . " uploaded successfully." . "</h3>";
                    //  readfile($_FILES['filename']['tmp_name']);
                }

                //Import uploaded file to Database
                $handle = fopen($_FILES['filename']['tmp_name'], "r");
                $i = 1;

                while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                    $num = count($data);

                    if ($i > 1) {
                        //// check value
                        $Postid = $data[0];

                        $meta_values = get_post_meta($Postid, $key, $single);

                        if (array_key_exists('meta_description', $meta_values) && array_key_exists('title_tag', $meta_values)) {
                            echo "&nbsp;";
                        } else {
                            add_post_meta($Postid, get_option("custom_title_key"), $value);
                            add_post_meta($Postid, get_option("custom_meta_description_key"), $Value_meta);
                        }
                        //$data = str_replace('"',"",$data[0]);
                        //$data = explode(',',$data);

                        $meta_description_key = get_option("custom_meta_description_key");
                        $meta_title_key = get_option("custom_title_key");

//$import="INSERT into wp_postmeta(post_id,meta_key,meta_value) values('".addslashes($data[0])."','".addslashes($data[1])."','".addslashes($data[2])."')";
//$import="INSERT into wp_postmeta(post_id,meta_key,meta_value) values('".addslashes($data[0])."','".addslashes($data[1])."','".addslashes($data[2])."')";
                        $import_title = "UPDATE " . $wpdb->prefix . "postmeta SET meta_value = '" . addslashes($data[1]) . "' WHERE post_id = '" . addslashes($data[0]) . "' and meta_key = '" . $meta_title_key . "'";
                        $import_meta_description = "UPDATE " . $wpdb->prefix . "postmeta SET meta_value = '" . addslashes($data[2]) . "' WHERE post_id = '" . addslashes($data[0]) . "' and meta_key = '" . $meta_description_key . "'";
                        $import_post_slug = "UPDATE " . $wpdb->prefix . "posts SET post_name = '" . addslashes($data[3]) . "' WHERE id = '" . addslashes($data[0]) . "'";

                        mysql_query($import_title) or die(mysql_error());
                        mysql_query($import_meta_description) or die(mysql_error());
                        mysql_query($import_post_slug) or die(mysql_error());
                    } else {
                        echo $sucess_msg = "<h3 style='color:green;'>CSV File Data Successfully Inserted.</h3>";
                    }

                    $i++;
                }

                fclose($handle);
            }
        } else {
            echo "<h2 style='color:red';>Invalid File! Please Select Valid CSV File to upload</h2>";
        }
    }
    ?>
            <!-- End Funcations for csv file upload -->
            <?php $url = plugins_url(); ?>
            <h3>Upload Titles and Meta Descriptions directly through a CSV File:</h3>
            <!-- for csv file upload -->
            <form enctype='multipart/form-data' action='<?php echo $_SERVER["REQUEST_URI"]; ?>'  method='post'>
                <table border="0" cellpadding="0" cellspacing="0">
                    <tr>
                        <td scope="row">Download current CSV file for editing:</td>
                        <td><input type='submit' name='download' value='Download'></td>
                    </tr>
                    <tr>
                        <td scope="row">Select a CSV file to upload:</td>
                        <td><input size='30' type='file' name='filename'>
                            <input type='submit' name='upload' value='Upload'> <br/>

                        </td>
                    </tr>
                </table>
            </form>
            <!-- end csv upload in tool page -->

    <?php
    //do the nav menu items for the subsubmenu
    if (empty($_REQUEST['title_tags_type'])) {
        $_REQUEST['title_tags_type'] = 'pages';
    }

    echo '<ul id="subsubmenu">' . "\n";
    echo '<li ' . is_current($_REQUEST['title_tags_type'], 'pages') . '><a href="?' . $_SERVER['QUERY_STRING'] . '&title_tags_type=pages">Pages</a></li>' . "\n";
    echo '<li ' . is_current($_REQUEST['title_tags_type'], 'posts') . '><a href="?' . $_SERVER['QUERY_STRING'] . '&title_tags_type=posts">Posts</a></li>' . "\n";
    echo '<li ' . is_current($_REQUEST['title_tags_type'], 'categories') . '><a href="?' . $_SERVER['QUERY_STRING'] . '&title_tags_type=categories">Categories</a></li>' . "\n";
    echo '<li ' . is_current($_REQUEST['title_tags_type'], 'tags') . '><a href="?' . $_SERVER['QUERY_STRING'] . '&title_tags_type=tags">Tags</a></li>' . "\n";
    echo '<li ' . is_current($_REQUEST['title_tags_type'], 'urls') . '><a href="?' . $_SERVER['QUERY_STRING'] . '&title_tags_type=urls">URLs</a></li>' . "\n";
    echo '</ul>' . "\n";

    // Render Page and Post Tabs
    if ($title_tags_type == 'pages' || $title_tags_type == 'posts') {
        $post_type = substr($title_tags_type, 0, -1); // Database table uses singular version
        ?>
                <p>Use the form below to enter or update a custom <?php echo $post_type; ?> title.<br /></p>
        <?php
        if (!empty($search_value)) {
            if ($page_no > 0) {
                // $limit = ' LIMIT ' . ($page_no * $manage_elements_per_page) . ', ' . $manage_elements_per_page;
                $limit = 10;
            } else {
                //  $limit = ' LIMIT ' . $manage_elements_per_page;
                $limit = 10;
            }

            $posts = $wpdb->get_results('SELECT * FROM ' . $wpdb->posts . ' WHERE post_type = \'' . $post_type . '\' ORDER BY menu_order ASC' . ('posts' == $title_tags_type ? ', post_date DESC' : ', ID ASC') . $limit);
        } else {

            $posts = $wpdb->get_results('SELECT * FROM ' . $wpdb->posts . ' WHERE post_type = \'' . $post_type . '\' ORDER BY menu_order ASC' . ('posts' == $title_tags_type ? ', post_date DESC' : ', ID ASC'));
            $new_posts;

            foreach ($posts as $post) {
                if (isset($post->post_type) and ($post->post_type != $post_type)) {
                    continue;
                }

                if (empty($search_value)) {
                    // No search value, add all
                    $new_posts[] = $post;
                } else {
                    // Filter based on search value
                    if (preg_match('/' . $search_value . '/i', $post->post_title)) {
                        $new_posts[] = $post;
                    } else {
                        $post_custom = get_post_custom($post->ID);

                        if (
                            preg_match('/' . $search_value . '/i', $post_custom[get_option("custom_title_key")][0]) ||
                            preg_match('/' . $search_value . '/i', $post->post_content) ||
                            preg_match('/' . $search_value . '/i', $post->post_excerpt)
                        ) {
                            $new_posts[] = $post;
                        }
                    }
                }
            }

            $posts = $new_posts;
            $element_count = count($posts);

            if (($element_count > $manage_elements_per_page) and (($page_no != 'all') or empty($page_no))) {
                if ($page_no > 0) {
                    $posts = array_splice($posts, ($page_no * $manage_elements_per_page));
                }

                $posts = array_slice($posts, 0, $manage_elements_per_page);
            }
        }

        if ($posts) {
            ?>
                    <form name="posts-form" action="<?php echo $_SERVER['REQUEST_URI'] ?>" method="post">
                    <?php
                    if (function_exists('wp_nonce_field')) {
                        wp_nonce_field('seo-title-tag-action_posts-form');
                    }
                    ?>
                        <input type="hidden" name="action" value="<?php echo $title_tags_type; ?>" />
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th scope="col">ID</th>
                                    <th scope="col">Title</th>
                                    <th scope="col">Custom Title and Meta Description</th>
                                    <th scope="col">Slug</th>
                                </tr>
                            </thead>
                            <tbody>
                    <?php
                    manage_seo_title_tags_recursive($title_tags_type, $posts);

                    echo '</table><br /><input type="submit" class="button" value="Submit" /></form>';
                } else {
                    echo '<p><b>No ' . $title_tags_type . ' found!</b></p>';
                }

                // Render Categories Tab
            } elseif ($title_tags_type == 'categories' || $title_tags_type == 'tags') {
                $singular = ('tags' == $title_tags_type ? 'tag' : 'category');

                $taxonomy = ('tags' == $title_tags_type ? 'post_tag' : 'category');
                ?>
                        <p>Use the form below to enter or update a custom <?php echo $singular; ?> title.<br /></p>
        <?php
        $terms = seo_title_tag_get_taxonomy($taxonomy);
        $table_name = $wpdb->prefix . "seo_title_tag_" . $singular;
        $term_titles = array();
        /*
          if (get_option("use_category_description_as_title") && 'categories' == $title_tags_type) {


          foreach ($terms as $category) {
          print_r($term_titles[$category->term_id] = $category->category_description );
          }
          } else { */
        // defult filling of the category titles field.
        $sql = 'SELECT ' . $singular . '_id as term_id, title,description FROM ' . $table_name;

        $results = $wpdb->get_results($sql);
        $term_titles = array();
        $term_description = array();

        foreach ($results as $term) {
            $term_titles[$term->term_id] = $term->title;
            $term_description[$term->term_id] = $term->description;
        }

        $terms_new = array();

        if ($terms) {
            foreach ($terms as $term) {
                $term->title = (isset($term_titles[$term->term_id]) ? $term_titles[$term->term_id] : '');
                $term->description = (isset($term_description[$term->term_id]) ? $term_description[$term->term_id] : '');

                if (empty($search_value)) {
                    $terms_new[] = $term;
                } else {
                    if (
                        preg_match('/' . $search_value . '/i', $term->title) ||
                        preg_match('/' . $search_value . '/i', $term->name)
                    ) {
                        $terms_new[] = $term;
                    }
                }
            }

            $terms = $terms_new;
        }
        // }

        $element_count = count($terms);

        if (($element_count > $manage_elements_per_page) and (($page_no != 'all') or empty($page_no))) {
            if ($page_no > 0) {
                $terms = array_splice($terms, ($page_no * $manage_elements_per_page));
            }

            $terms = array_slice($terms, 0, $manage_elements_per_page);
        }

        if ($terms) {
            ?>
                            <form name="categories-form" action="<?php echo $_SERVER['REQUEST_URI'] ?>" method="post">
                            <?php
                            if (function_exists('wp_nonce_field')) {
                                wp_nonce_field('seo-title-tag-action_taxonomy-form');
                            }
                            ?>
                                <input type="hidden" name="action" value="<?php echo $title_tags_type; ?>" />
                                <table class="widefat">
                                    <thead>
                                        <tr>
                                            <th scope="col">ID</th>
                                            <th scope="col"><?php echo ucfirst($singular); ?></th>
                                            <th scope="col">Custom Title and Meta Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                            <?php
                            foreach ($terms as $term) {
                                $term_href = ('tags' == $title_tags_type ? get_tag_link($term->term_id) : get_category_link($term->term_id));
                                ?>
                                            <tr>

                                                <td><a href="<?php echo $term_href ?>"><?php echo $term->term_id ?></a></td>
                                                <td><?php echo $term->name ?></td>
                                                <td><span style="width:110px;float:left; padding-right: 5px;">Title </span><input type="text" name="title_<?php echo $term->term_id ?>" value="<?php echo esc_html($term->title); ?>" size="80" /><br />
                                                    <span style="width:110px;float:left; padding-right: 5px;">Meta Description</span><input type="text" name="description_<?php echo $term->term_id ?>" value="<?php echo esc_html($term->description); ?>" size="80" />

                                                </td>
                                    <?php
                                }

                                echo '</table><br /><input type="submit" class="button" value="Submit" /></form>';
                            } else { //End of check for terms
                                print "<b>No " . ucfirst($title_tags_type) . " found!</b>";
                            }
                        } elseif ($title_tags_type == 'urls') {
                            ?>
                                <p>Use the form below to enter or update a title tag for any URL, including archives pages, tag conjunction pages, etc.</p><p>In the URL field, leave off the http:// and your domain and your blog's directory (if you have one). e.g. <i>tag/seo+articles</i> is okay; <i>http://www.netconcepts.com/tag/seo+articles</i> is NOT.<br /></p>
        <?php
        $table_name = $wpdb->prefix . "seo_title_tag_url";
        $urls;

        $sql = 'SELECT id, url, title,description from ' . $table_name;

        if (!empty($search_value)) {
            $sql .= ' WHERE url LIKE "%' . $wpdb->escape($search_value) . '%" OR title LIKE "%' . $wpdb->escape($search_value) . '%"';
        }

        $sql .= ' ORDER BY title';
        $urls = $wpdb->get_results($sql);
        $element_count = count($urls);

        if (($element_count > $manage_elements_per_page) and (($page_no != 'all') or empty($page_no))) {
            if ($page_no > 0) {
                $urls = array_splice($urls, ($page_no * $manage_elements_per_page));
            }

            $urls = array_slice($urls, 0, $manage_elements_per_page);
        }
        ?>
                                <form name="urls-form" action="<?php echo $_SERVER['REQUEST_URI'] ?>" method="post">
                                <?php
                                if (function_exists('wp_nonce_field')) {
                                    wp_nonce_field('seo-title-tag-action_urls-form');
                                }
                                ?>
                                    <input type="hidden" name="action" value="urls" />
                                    <table class="widefat">
                                        <thead>
                                            <tr>
                                                <th scope="col">ID</th>
                                                <th scope="col">URL</th>
                                                <th scope="col">Custom Title and Meta Description</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                <?php
                                if (is_array($urls)) {
                                    foreach ($urls as $url) {
                                        $url_value = $url->title;
                                        $url_value_meta_description = $url->description;

                                        if (get_magic_quotes_runtime()) {
                                            $url_value = stripslashes($url_value);
                                        }
                                        ?>
                                                    <tr>
                                                        <td><a href="/<?php echo preg_replace('/^\//', '', $url->url) ?>"><?php echo $url->id ?></a></td>
                                                        <td><input type="text" title="<?php echo esc_html($url->url) ?>" name="url_<?php echo $url->id ?>" value="<?php echo esc_html($url->url) ?>" size="40" /></td>
                                                        <td>
                                                            <span style="width:110px;float:left; padding-right: 5px;">Title</span><input type="text" title="<?php echo wp_specialchars($url->title, true) ?>" name="title_<?php echo $url->id ?>" value="<?php echo esc_html($url_value); ?>" size="70" /><br />
                                                            <span style="width:110px;float:left; padding-right: 5px;">Meta Description</span><input type="text" title="<?php echo esc_html($url->description) ?>" name="meta_description_<?php echo $url->id ?>" value="<?php echo esc_html($url_value_meta_description); ?>" size="70" />

                                                        </td>
                                                    </tr>
                <?php
            }
        }

        for ($n = 0; $n < 5; $n++) {
            ?>
                                                <tr>
                                                <td>New <!-- (<?php // echo ($n + 1)  ?>) --> </td>
                                                    <td><input type="text" name="url_new_<?php echo $n ?>" value="" size="40" /></td>
                                                    <td><span style="width:110px;float:left; padding-right: 5px;">Title</span><input type="text" name="title_new_<?php echo $n ?>" value="" size="70" /><br />
                                                        <span style="width:110px;float:left; padding-right: 5px;"> Meta Description</span><input type="text" name="meta_description_new_<?php echo $n ?>" value="" size="70" />
                                                    </td>
                                                </tr>
                                                <?php
                                            }

                                            echo '</table><br /><input type="submit" class="button" value="Submit" /></form>';
                                        } else {
                                            echo '<p>unknown title tags type!</p>';
                                        }
                                        ?>

    <?php
    if ($element_count > $manage_elements_per_page) {
        if (($page_no == 'all') and (!empty($page_no))) {
            //  echo 'View All&nbsp;&nbsp;';
        } else {
            // echo '<a href="'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'].'&page_no=all&title_tags_type='.$title_tags_type.$search_query_string.'">View All</a>&nbsp;&nbsp;';
        }
    }
    ?>
                                    <style>
                                        .pagination{float:right;}
                                        .pagination a{border:1px solid #ccc;padding:4px 8px;}
                                    </style>
    <?php
    echo "<div class='pagination'>";

    if ($element_count > $manage_elements_per_page) {
        $max = (int) ceil($element_count / $manage_elements_per_page);
        $inital = 0;

        if ($max > 5) {
            $max = 5;
        }

        if ($element_count > ($page_no * $manage_elements_per_page) && $page_no >= 4) {
            $inital = $page_no - (($page_no + 1) % 5);
            $max = (int) ceil($element_count / $manage_elements_per_page);

            if ($max > ($inital + 5)) {
                $max = $inital + 5;
            }

            echo '<a href="' . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . '&page_no=' . ($page_no - 1) . '&title_tags_type=' . $title_tags_type . $search_query_string . '"><< Prev</a> ';
        }

        for ($p = $inital; $p < $max; $p++) {
            if ($page_no == $p) {
                echo ($p + 1) . '&nbsp;';
            } else {
                echo '<a href="' . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . '&page_no=' . $p . '&title_tags_type=' . $title_tags_type . $search_query_string . '">' . ($p + 1) . '</a> ';
            }
        }

        if ($page_no != ($max - 1) && $max >= 5) {
            echo '<a href="' . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . '&page_no=' . ($page_no + 1) . '&title_tags_type=' . $title_tags_type . $search_query_string . '">Next >> </a> ';
        }
    }
    
    echo "</div>";
    ?>
                                    </div>
                                    <?php
                                }

                                function manage_seo_title_tags_recursive($type, $elements = 0)
                                {
                                    if (!$elements) {
                                        return;
                                    }

                                    $cache = array();

                                    foreach ($elements as $element) {
                                        $level = 0;

                                        $element_custom = get_post_custom($element->ID);

                                        $pad = str_repeat('&#8212; ', $level);
                                        $element_value = $element_custom[get_option("custom_title_key")][0];
                                        $element_value_meta = $element_custom[get_option("custom_meta_description_key")][0];

                                        if (get_magic_quotes_runtime()) {
                                            $element_value = stripslashes($element_value);
                                        }

                                        if (get_magic_quotes_runtime()) {
                                            $element_value_meta = stripslashes($element_value_meta);
                                        }
                                        ?>
                                        <tr>
                                            <td><a href="<?php echo get_permalink($element->ID) ?>"><?php echo $element->ID ?></a></td>
                                            <td><?php echo $pad . $element->post_title ?></td>
                                            <td><span style="width:110px;float:left; padding-right: 5px;">Title</span><input type="text" title="<?php echo esc_html($element->post_title) ?>" name="tagtitle_<?php echo $element->ID ?>" id="tagtitle_<?php echo $element->ID ?>" value="<?php echo esc_html($element_value); ?>" size="70" /><br />
                                                <!--meta description -->
                                                <span style="width:110px;float:left; padding-right: 5px;">Meta Description</span><input type="text" title="<?php echo esc_html($element->post_title) ?>" name="tagdescription_<?php echo $element->ID ?>" id="tagdescription_<?php echo $element->ID ?>" value="<?php echo esc_html($element_value_meta); ?>" size="70" />
                                            </td>

                                        <?php if ('pages' == $type || 'posts' == $type): ?>
                                                <td><input type="text" title="<?php echo esc_html($element->post_title) ?>" name="post_name_<?php echo $element->ID ?>" id="post_name_<?php echo $element->ID ?>" value="<?php echo esc_html($element->post_name); ?>" size="20" /></td>
                                        <?php endif; ?>
                                        <?php
                                    }
                                }

// returns class=current if the strings exist and match else nothing.
// Used down on the top nav to select which page is selected.
                                function is_current($aRequestVar, $aType)
                                {
                                    if (!isset($aRequestVar) || empty($aRequestVar)) {
                                        return;
                                    }

                                    //do the match
                                    if ($aRequestVar == $aType) {
                                        return 'class=current';
                                    }
                                }
