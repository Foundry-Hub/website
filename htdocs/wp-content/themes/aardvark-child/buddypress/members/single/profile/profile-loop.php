<?php
/**
 * BuddyPress - Members Profile Loop
 *
 * @package BuddyPress
 * @subpackage bp-legacy
 * @version 3.0.0
 */

/** This action is documented in bp-templates/bp-legacy/buddypress/members/single/profile/profile-wp.php */
do_action( 'bp_before_profile_loop_content' ); ?>

<?php if ( bp_has_profile() ) : ?>

	<?php while ( bp_profile_groups() ) : bp_the_profile_group(); ?>

		<?php if ( bp_profile_group_has_fields() ) : ?>

			<?php

			/** This action is documented in bp-templates/bp-legacy/buddypress/members/single/profile/profile-wp.php */
			do_action( 'bp_before_profile_field_content' ); ?>

			<div class="bp-widget <?php bp_the_profile_group_slug(); ?>">

				<h2><?php bp_the_profile_group_name(); ?></h2>

				<table class="profile-fields">

					<?php while ( bp_profile_fields() ) : bp_the_profile_field(); ?>

						<?php if ( bp_field_has_data() ) : ?>

							<tr<?php bp_field_css_class(); ?>>

								<td class="label"><?php bp_the_profile_field_name(); ?></td>

								<td class="data"><?php bp_the_profile_field_value(); ?></td>

							</tr>

						<?php endif; ?>

						<?php

						/**
						 * Fires after the display of a field table row for profile data.
						 *
						 * @since 1.1.0
						 */
						do_action( 'bp_profile_field_item' ); ?>

					<?php endwhile; ?>

				</table>
			</div>

			<?php

			/** This action is documented in bp-templates/bp-legacy/buddypress/members/single/profile/profile-wp.php */
			do_action( 'bp_after_profile_field_content' ); ?>

		<?php endif; ?>

	<?php endwhile; ?>

	<?php
	//Display endorsements if enabled
	$showEndorsements = bp_get_profile_field_data([
		'field' => 'Public Endorsements'
	]);
	$showEndorsements = bp_is_my_profile() || $showEndorsements === "" || $showEndorsements == "Yes";

	//Get the endorsements
	if($showEndorsements){
		$displayedUserID = bp_displayed_user_id();
		if(false === ($userEndorsements = get_transient("userEndorsements_".$displayedUserID))){

			$sql = "SELECT wp_posts.post_name, wp_posts.post_title, wp_posts.post_type
					FROM wp_posts
					JOIN wp_posts_users_meta ON wp_posts.ID = wp_posts_users_meta.post_id
					WHERE wp_posts_users_meta.meta_key = 'endorsed' AND wp_posts_users_meta.user_id = $displayedUserID
					ORDER BY wp_posts.post_type, wp_posts.post_title";
			$endorsementsQuery = $wpdb->get_results($sql, ARRAY_A);
			$userEndorsements = [
				"creators" => [],
				"packages" => []
			];
			foreach($endorsementsQuery as $endorsement){
				if($endorsement['post_type'] == "creator")
					$userEndorsements['creators'][] = $endorsement;
				else
					$userEndorsements['packages'][] = $endorsement;
			}
			set_transient("userEndorsements_".$displayedUserID, $userEndorsements, DAY_IN_SECONDS);
		}
		if(!empty($userEndorsements['creators'])||!empty($userEndorsements['packages'])){
			?>
			<div class="bp-widget endorsementList">
				<h2>User endorsements</h2>
				<?php
				if(!empty($userEndorsements['packages'])){
					?>
					<h3>Packages</h3>
					<ul>
					<?php
						$list = '';
						foreach($userEndorsements['packages'] as $pkg){
							$list .= "<li><a href='/package/{$pkg['post_name']}'>{$pkg['post_title']}</a></li>";
						}
						echo $list;
					?>
					</ul>
				<?php
				}
				if(!empty($userEndorsements['creators'])){
					?>
					<h3>Creators</h3>
					<ul>
					<?php
						$list = '';
						foreach($userEndorsements['creators'] as $pkg){
							$list .= "<li><a href='/creator/{$pkg['post_name']}'>{$pkg['post_title']}</a></li>";
						}
						echo $list;
					?>
					</ul>
				<?php
				}
				?>
			</div>
	<?php
		}
	}
	/** This action is documented in bp-templates/bp-legacy/buddypress/members/single/profile/profile-wp.php */
	do_action( 'bp_profile_field_buttons' ); ?>

<?php endif; ?>

<?php

/** This action is documented in bp-templates/bp-legacy/buddypress/members/single/profile/profile-wp.php */
do_action( 'bp_after_profile_loop_content' );
