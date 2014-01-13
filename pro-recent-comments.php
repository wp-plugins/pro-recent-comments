<?php
/*
Plugin Name: Pro Recent Comments
Plugin URI: http://wordpress.org/extend/plugins/pro-recent-comments/
Description: Pro Recent Comments Widget plugin.You have choice to customize your most recent comments.
Version: 1.1
Author: Shambhu Prasad Patnaik
Author URI:http://socialcms.wordpress.com/
*/
class Pro_Recent_Comments_Widget extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
	 		'pro-recent-comments', // Base ID
			'Pro Recent Comments', // Name
			array( 'description' => __( 'customize your most recent comments', 'text_domain' ), 'classname' => 'widget_recent_comments')); // Args

			if ( is_active_widget(false, false, $this->id_base) )
			add_action( 'wp_head', array($this, 'recent_comments_style') );

		add_action( 'comment_post', array($this, 'flush_widget_cache') );
		add_action( 'edit_comment', array($this, 'flush_widget_cache') );
		add_action( 'transition_comment_status', array($this, 'flush_widget_cache') );
		
	}
	 function recent_comments_style() {
		if ( ! current_theme_supports( 'widgets' ) // Temp hack #14876
			|| ! apply_filters( 'show_recent_comments_widget_style', true, $this->id_base ) )
			return;
		?>
	<style type="text/css">.recentcomments a{display:inline !important;padding:0 !important;margin:0 !important;}</style>
<?php
	}

	function flush_widget_cache() {
		wp_cache_delete('widget_pro_recent_comments', 'widget');
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	 function filter_where_clause($w,$q)
	 {
		 
		 if(isset($q->query_vars['post_ids']) &&  $q->query_vars['post_ids']!='')
		 $w['where'].=" AND comment_post_ID in (". $q->query_vars['post_ids']. ")";
		 if(isset($q->query_vars['exclude_post']) &&  $q->query_vars['exclude_post']!='')
		 $w['where'].=" AND comment_post_ID  not in (". $q->query_vars['exclude_post']. ")";		 
		 return $w;
	 }
	public function widget( $args, $instance ) {
		global $comments, $comment;
		

		$cache = wp_cache_get('widget_pro_recent_comments', 'widget');

		 if ( ! is_array( $cache ) )
			$cache = array();

		if ( ! isset( $args['widget_id'] ) )
			$args['widget_id'] = $this->id;

		if ( isset( $cache[ $args['widget_id'] ] ) ) {
			echo $cache[ $args['widget_id'] ];
			return;
		}

 		extract($args, EXTR_SKIP);
 		$output = '';

		$title = ( ! empty( $instance['title'] ) ) ? $instance['title'] : __( 'Recent Comments' );
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );
		$number = ( ! empty( $instance['number'] ) ) ? absint( $instance['number'] ) : 5;
		$post_ids = ( ! empty( $instance['post_ids'] ) ) ? strip_tags( $instance['post_ids'] ) : '';
		$exclude_post = ( ! empty( $instance['exclude_post'] ) ) ? absint( $instance['exclude_post'] ) : 0;
		if ( ! $number )
 			$number = 5;
        if($post_ids!='' ||  $exclude_post >0)
        add_filter( 'comments_clauses', array (__CLASS__,'filter_where_clause'),'',2);  
		
		$comments = get_comments( apply_filters( 'widget_comments_args', array( 'number' => $number, 'status' => 'approve', 'post_status' => 'publish' ,'post_ids' => $post_ids,'exclude_post' => $exclude_post) ) );

		$output .= $before_widget;
		if ( $title )
			$output .= $before_title . $title . $after_title;

		$output .= '<ul id="recentcomments">';
		if ( $comments ) {
			// Prime cache for associated posts. (Prime post term cache if we need it for permalinks.)
			$post_ids = array_unique( wp_list_pluck( $comments, 'comment_post_ID' ) );
			_prime_post_caches( $post_ids, strpos( get_option( 'permalink_structure' ), '%category%' ), false );

			foreach ( (array) $comments as $comment) {
				$output .=  '<li class="recentcomments">' . /* translators: comments widget: 1: comment author, 2: post link */ sprintf(_x('%1$s on %2$s', 'widgets'), get_comment_author_link(), '<a href="' . esc_url( get_comment_link($comment->comment_ID) ) . '">' . get_the_title($comment->comment_post_ID) . '</a>') . '</li>';
			}
 		}
		$output .= '</ul>';
		$output .= $after_widget;

		echo $output;
		$cache[$args['widget_id']] = $output;
		wp_cache_set('widget_pro_recent_comments', $cache, 'widget');
	}
	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['number'] = absint( $new_instance['number'] );
		$instance['post_ids'] = strip_tags( $new_instance['post_ids'] );
		$instance['exclude_post'] = absint( $new_instance['exclude_post'] );
		$this->flush_widget_cache();

		$alloptions = wp_cache_get( 'alloptions', 'options' );
		if ( isset($alloptions['widget_pro_recent_comments']) )
			delete_option('widget_pro_recent_comments');

		return $instance;
	}

	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
		$title        = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : '';
		$number       = isset( $instance['number'] ) ? absint( $instance['number'] ) : 5;
		$post_ids     = (isset( $instance['post_ids'] ) ) ? esc_attr( $instance['post_ids'] ) : '';
		$exclude_post = isset( $instance['exclude_post'] ) ? absint( $instance['exclude_post'] ) : '';
?>
		<p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" /></p>

		<p><label for="<?php echo $this->get_field_id( 'number' ); ?>"><?php _e( 'Number of comments to show:' ); ?></label>
		<input id="<?php echo $this->get_field_id( 'number' ); ?>" name="<?php echo $this->get_field_name( 'number' ); ?>" type="text" value="<?php echo $number; ?>" size="3" /></p>

		<p><label for="<?php echo $this->get_field_id( 'post_ids' ); ?>"><?php _e( 'Post IDs :' ); ?></label>
		<input id="<?php echo $this->get_field_id( 'post_ids' ); ?>" class="widefat"  name="<?php echo $this->get_field_name( 'post_ids' ); ?>" type="text" value="<?php echo $post_ids; ?>" size="3" />
		<br>Enter a comma separated Post Id's.<br>ex : <code>2,3</code> &nbsp;&nbsp;(This widget will display only that post comments).</p>

		<p><label for="<?php echo $this->get_field_id( 'exclude_post' ); ?>" ><?php _e( 'Exclude Post Id :' ); ?></label>
		<input id="<?php echo $this->get_field_id( 'exclude_post' ); ?>"  class="widefat"  name="<?php echo $this->get_field_name( 'exclude_post' ); ?>" type="text" value="<?php echo $exclude_post; ?>" size="3" />
		<br>(This widget will display all comments except these post id).</p>
<?php
	}
} // class pro-recent-comments

// register Pro_Recent_Comments_Widget widget
add_action( 'widgets_init', create_function( '', 'register_widget( "Pro_Recent_Comments_Widget" );' ) );
register_deactivation_hook(__FILE__, 'pro_recent_comments_widget_deactivate');

if (!function_exists('pro_recent_comments_widget_deactivate')):
function pro_recent_comments_widget_deactivate ()
{
 unregister_widget('Pro_Recent_Comments_Widget');
}
endif;
?>