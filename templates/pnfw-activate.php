<?php
// Template Name: Token activate

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

pnfw_get_template_part('header'); ?>

<html>
	<head>
		<?php
		function is_mobile() {
			return preg_match("/(android|iphone|ipad|silk|kindle)/i", $_SERVER["HTTP_USER_AGENT"]);
		}

		$pnfw_url_scheme = get_option("pnfw_url_scheme");
		if (!$error && is_mobile() && $pnfw_url_scheme) { ?>
			<meta http-equiv="refresh" content="3;url=<?php echo $pnfw_url_scheme; ?>://" />
		<?php } ?>
	</head>

	<body>
		<table width="100%" height="100%" border="0" style="margin: 1.5rem 0rem 1.5rem 0rem">
			<tr height="100%">
				<td width="100%" valign="center" align="center">
					<div id="content" class="content">
						<img id="loading-spinner" class="mt-4 mb-4" src="<?php echo PNFW_Push_Notifications_for_WordPress_Lite::instance()->assets_url() . '/imgs/wpspin.gif'; ?>" />
						<p id="result" hidden></p>
					</div>
				</td>
			</tr>
		</table>
	</body>
</html>

<?php pnfw_get_template_part('footer'); ?>
