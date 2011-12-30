<?php
/*
Plugin Name: BostInno Comment @mentions
Plugin URI: http://www.bostinno.com
Description: Adds the ability to "@mention" other registered users on the site within a comment.
Author: Brian Zeligson, Kevin McCarthy
Author URI: http://www.bostinno.com
Version: 0.1
License: GPL
*/

define( 'BINNOMENTIONS_URL', plugin_dir_url(__FILE__) );
define( 'BINNOMENTIONS_PATH', plugin_dir_path(__FILE__) );
define( 'BINNOMENTIONS_BASENAME', plugin_basename( __FILE__ ) );

class BInnoCommentMentionsPlugin
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_binno_mentions_js'));
        add_action('wp_ajax_binno_comment_mentions', array($this, 'binno_comment_process'));
        add_action('wp_ajax_nopriv_binno_comment_mentions', array($this, 'binno_comment_process'));
        add_action('wp_ajax_mentions_get_users_as_json', array($this, 'get_users_as_json'));
        add_action('wp_ajax_nopriv_mentions_get_users_as_json', array($this, 'get_users_as_json'));
    }
    
    private function getIP()
    { 
        $ip; 
        if (getenv("HTTP_CLIENT_IP")) 
        $ip = getenv("HTTP_CLIENT_IP"); 
        else if(getenv("HTTP_X_FORWARDED_FOR")) 
        $ip = getenv("HTTP_X_FORWARDED_FOR"); 
        else if(getenv("REMOTE_ADDR")) 
        $ip = getenv("REMOTE_ADDR"); 
        else 
        $ip = "UNKNOWN";
        return $ip; 
        
    }

    
    public function binno_comment_process()
    {
        check_ajax_referer('binnoMentions', 'security');
        $time = current_time('mysql');
        $userdata = (is_user_logged_in()) ? get_userdata(get_current_user_id()) : false;
        $user_id = ($userdata) ? $userdata->ID : false;
        $author = ($userdata) ? $userdata->display_name : $_POST['author'];
        $author_email = ($userdata) ? $userdata->user_email : $_POST['email'];
        $author_url = ($userdata) ? $userdata->user_url : $_POST['url'];
        $moderating = get_option('comment_moderation');
        $commentdata = array(
            'comment_post_ID' => $_POST['comment_post_ID'],
            'comment_author' => $author,
            'comment_author_email' => $author_email,
            'comment_author_url' => $author_url,
            'comment_type' => '',
            'comment_parent' => $_POST['comment_parent'],
            'user_id' => $user_id,
            'comment_author_IP' => $this->getIP(),
            'comment_agent' => $_SERVER['HTTP_USER_AGENT'],
            'comment_date' => $time,
            'comment_approved' => $moderating
        );
        $comment_content = $_POST['comment'];
        $bcm_object = new BInnoCommentMentions($_POST['mentions'], $commentdata);
        $comment_content = apply_filters('binno_mentions_pre_comment_process', $comment_content, $bcm_object);
        if ($bcm_object->processDefault) $this->link_mentions(&$comment_content, $_POST['mentions']);
        $commentdata['comment_content'] = $comment_content;
        $new_comment_id = wp_new_comment($commentdata);
        $bcm_object->update_comment($comment_content, $new_comment_id);
        $bcm_object->process_mentions($new_comment_id);
        do_action('binno_mentions_post_comment_process', $bcm_object);
        die();
    }
    
    public function link_mentions(&$comment_content, $mentions)
    {
        foreach($mentions as $mention)
        {
            $data = get_userdata($mention);
            $comment_content = preg_replace('/'.$data->display_name.'/', '<a href="'.get_author_posts_url($mention).'">'.$data->display_name.'</a>', $comment_content, 1);
        }
    }
    
    public function enqueue_binno_mentions_js()
    {
        wp_enqueue_script('binno_jq_cookie', BINNOMENTIONS_URL.'/js/jquery.cookie.js', array('jquery'));
        wp_enqueue_script('binno_jq_elastic', BINNOMENTIONS_URL.'/jq_mentions_input/lib/jquery.elastic.js', array('jquery'));
        wp_enqueue_script('binno_underscore_js', BINNOMENTIONS_URL.'/jq_mentions_input/lib/underscore.js', array('jquery'));
        wp_enqueue_script('binno_jq_eventsinput', BINNOMENTIONS_URL.'/jq_mentions_input/lib/jquery.events.input.js', array('jquery'));
        wp_enqueue_script('binno_jq_mentions', BINNOMENTIONS_URL.'/jq_mentions_input/jquery.mentionsInput.js', array('jquery'));
        wp_enqueue_style('binno_jq_mentions', BINNOMENTIONS_URL.'/jq_mentions_input/jquery.mentionsInput.css');
        wp_enqueue_script('binno_mentions_js', BINNOMENTIONS_URL.'/js/binno.mentions.js', array(
                                                'jquery', 'binno_jq_cookie', 'binno_jq_mentions', 'binno_jq_elastic', 'binno_jq_eventsinput', 'binno_underscore_js'));
        $jsParams = array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'binnoMentionsNonce' => wp_create_nonce('binnoMentions'),
            'cookieHash' => COOKIEHASH,
            'cookiePath' => COOKIEPATH,
            'cookieDomain' => COOKIE_DOMAIN,
            'loggedIn' => is_user_logged_in(),
        );
        wp_localize_script('binno_mentions_js', 'binnoMentionsParams', $jsParams);
    }
    
    public function get_users_as_json()
    {
        check_ajax_referer('binnoMentions', 'security');
        $users = get_users();
        $return = array();
        foreach($users as $user)
        {
            $return[] = array('id' => $user->ID, 'name' => $user->display_name, 'type' => 'contact');
        }
        echo json_encode($return);
        die();
    }
}

class BInnoCommentMentions
{
    private $_mentions;
    private $_comment;
    public $processDefault;
    
    public function __construct($mentions, $comment)
    {
        $this->_mentions = $mentions;
        $this->_comment = $comment;
        $this->processDefault = true;
    }
    
    public function mentions()
    {
        return $this->_mentions;
    }
    
    public function comment()
    {
        return $this->_comment;
    }
    
    public function update_comment($comment_content, $comment_id)
    {
        $this->_comment['comment_content'] = $comment_content;
        $this->_comment['comment_id'] = $comment_id;
    }
    
    public function process_mentions($comment_id)
    {
        if (!empty($this->_mentions)) update_comment_meta($comment_id, 'mentions', $this->_mentions);
    }
    
}

add_action('init', 'initialize_binno_mentions');

function initialize_binno_mentions()
{
    $mentions = new BInnoCommentMentionsPlugin;
}