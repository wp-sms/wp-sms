<div class="wrap">

    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore
        magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo
        consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla
        pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est
        laborum.</p>

    <form method="post" action="">

        <div id="universal-message-container">
            <h2>Export the data</h2>

            <div class="options">
                <p>
                    <label>Mobile number</label>
                    <br/>
                    <input type="text" name="mobile-number" value=""/>
                </p>
            </div><!-- #universal-message-container -->

			<?php submit_button( 'Export' ); ?>
        </div>

        <div id="universal-message-container">
            <h2>Delete the data</h2>

            <div class="options">
                <p>
                    <label>What message would you like to display above each post?</label>
                    <br/>
                    <input type="text" name="mobile-number" value=""/>
                </p>
            </div><!-- #universal-message-container -->

			<?php submit_button( 'Delete' ); ?>
        </div>

    </form>

</div><!-- .wrap -->