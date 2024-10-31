<?php
/**
 * The template for displaying only single blog posts content, without HTML code.
 *
 * @package WordPress
 * @subpackage WP-Recognant
 * @since Twenty Fifteen 1.0
 */

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js">
<head>
<title><?php wp_title( '|', true, 'right' ); ?></title>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width">
</head>
<body <?php body_class(); ?>>
<div id="primary" class="content-area">
<?php
	// Start the loop.
	while ( have_posts() ) : the_post();
		echo '<h2>'.get_the_title().'</h2>'."\n\n";
		the_content();
	endwhile;
	?>
</div>
</body>
</html>