<?php

use Roots\Acorn\Application;

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| our theme. We will simply require it into the script here so that we
| don't have to worry about manually loading any of our classes later on.
|
*/

if (! file_exists($composer = __DIR__.'/vendor/autoload.php')) {
    wp_die(__('Error locating autoloader. Please run <code>composer install</code>.', 'sage'));
}

require $composer;

/*
|--------------------------------------------------------------------------
| Register The Bootloader
|--------------------------------------------------------------------------
|
| The first thing we will do is schedule a new Acorn application container
| to boot when WordPress is finished loading the theme. The application
| serves as the "glue" for all the components of Laravel and is
| the IoC container for the system binding all of the various parts.
|
*/

Application::configure()
    ->withProviders([
        App\Providers\ThemeServiceProvider::class,
    ])
    ->boot();

/*
|--------------------------------------------------------------------------
| Register Sage Theme Files
|--------------------------------------------------------------------------
|
| Out of the box, Sage ships with categorically named theme files
| containing common functionality and setup to be bootstrapped with your
| theme. Simply add (or remove) files from the array below to change what
| is registered alongside Sage.
|
*/

collect(['setup', 'filters'])
    ->each(function ($file) {
        if (! locate_template($file = "app/{$file}.php", true, true)) {
            wp_die(
                /* translators: %s is replaced with the relative file path */
                sprintf(__('Error locating <code>%s</code> for inclusion.', 'sage'), $file)
            );
        }
    });

    
/*
|--------------------------------------------------------------------------
| Modify wp-login.php
|--------------------------------------------------------------------------
|
| Personalizing the login and register page
| Pierre march 2025
|
*/

function custom_login_logo() { ?>
    <style type="text/css">
        body.login h1 a {
            background-image: url('<?php echo get_stylesheet_directory_uri(); ?>/resources/images/logo_polyglotchat.png');
            background-size: contain;
            width: 100%;
            height: 80px;
        }
    </style>
<?php }
add_action('login_enqueue_scripts', 'custom_login_logo');

/**
 * 
 * Changer login/register page logo URL 
 * 
 */
function custom_login_logo_url() {
    return home_url();
}
add_filter('login_headerurl', 'custom_login_logo_url');

function custom_login_logo_url_title() {
    return get_bloginfo('name');
}
add_filter('login_headertext', 'custom_login_logo_url_title');

/**
 * 
 * Adding a password field in register page
 * 
 */
function custom_register_form() { ?>
    <p>
        <label for="password">Mot de passe</label>
        <input type="password" name="password" id="password" required>
    </p>
<?php }
add_action('register_form', 'custom_register_form');

function custom_register_user($user_id) {
    if (!empty($_POST['password'])) {
        wp_set_password($_POST['password'], $user_id);
    }
}
add_action('user_register', 'custom_register_user');

/**
 * 
 * To hide pseudo (identifiant) field during registration
 * 
 */
function remove_username_field() { ?>
    <style>
        #registerform p:first-child { display: none; }
    </style>
<?php }
add_action('login_enqueue_scripts', 'remove_username_field');

/**
 * 
 * Generate automatically pseudo with email via PHP an JS
 * 
 */
function generate_username_from_email($user_login, $user_email) {
    $user_login = explode('@', $user_email)[0]; // Prendre la partie avant @
    
    // Vérifier si le nom est déjà pris
    $i = 1;
    $original_login = $user_login;
    while (username_exists($user_login)) {
        $user_login = $original_login . $i;
        $i++;
    }

    return $user_login;
}

function auto_generate_username($user_id) {
    $user = get_userdata($user_id);
    
    if (empty($user->user_login)) {
        $new_username = generate_username_from_email('', $user->user_email);
        
        global $wpdb;
        $wpdb->update(
            $wpdb->users,
            ['user_login' => $new_username],
            ['ID' => $user_id]
        );
    }
}
add_action('user_register', 'auto_generate_username');

function auto_fill_username_js() { 
    if (isset($_GET['action']) && $_GET['action'] === 'register') {
        ?>
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                let emailField = document.getElementById("user_email");
                let usernameField = document.getElementById("user_login");

                if (emailField && usernameField) {
                    emailField.addEventListener("input", function () {
                        let emailValue = emailField.value.split("@")[0]; // Prendre la partie avant @
                        usernameField.value = emailValue;
                    });
                }
            });
        </script>
        <?php 
    }
}
add_action('login_footer', 'auto_fill_username_js');

/**
 * 
 * Remove default email 
 * 
 */
remove_action('register_new_user', 'wp_send_new_user_notifications');
remove_action('edit_user_created_user', 'wp_send_new_user_notifications', 10, 2);

/**
 * 
 * To add user_status and send mail checking
 * 
 */

// Action pour gérer l'inscription
function handle_custom_user_registration($user_id) {

    // Ne pas envoyer d'email pour les utilisateurs déjà validés
    if (get_user_meta($user_id, 'user_status', true) === '1') {
        return;
    }

    // Générer un token unique pour la validation
    $validation_token = wp_generate_password(20, false);
    update_user_meta($user_id, 'validation_token', $validation_token);
    update_user_meta($user_id, 'user_status', 0); // 0 = non validé

    // Créer un lien de validation
    $validation_link = home_url("/?validate_user=1&user_id=$user_id&token=$validation_token");

    // Envoyer l'email de validation
    $user = get_userdata($user_id);
    $subject = "Validation de votre compte Polyglotchat";
    $message = "Bonjour " . $user->user_login . ",\n\nCliquez sur ce lien pour valider votre compte : $validation_link\n\nMerci !";

    wp_mail($user->user_email, $subject, $message);
}
add_action('user_register', 'handle_custom_user_registration', 10, 1);

// Fonction pour valider le compte de l'utilisateur
function validate_user_account() {
    if (isset($_GET['user_id']) && isset($_GET['token'])) {
        $user_id = intval($_GET['user_id']);
        $token = sanitize_text_field($_GET['token']);

        // Vérifier si le token est correct
        $saved_token = get_user_meta($user_id, 'validation_token', true);
        if ($token === $saved_token) {
            update_user_meta($user_id, 'user_status', 1); 
            delete_user_meta($user_id, 'validation_token'); 

            $login_url = wp_login_url();
            $message = "Votre compte a bien été validé ! Vous allez être redirigé vers la page de connexion...";
            
            echo "
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Créer une popup
                    let popup = document.createElement('div');
                    popup.style.position = 'fixed';
                    popup.style.top = '50%';
                    popup.style.left = '50%';
                    popup.style.transform = 'translate(-50%, -50%)';
                    popup.style.background = 'white';
                    popup.style.padding = '20px';
                    popup.style.border = '2px solid #4CAF50';
                    popup.style.boxShadow = '0px 0px 10px rgba(0, 0, 0, 0.2)';
                    popup.style.textAlign = 'center';
                    popup.style.fontSize = '16px';
                    popup.innerHTML = '<p>$message</p>';

                    document.body.appendChild(popup);

                    // Redirection après 3 secondes
                    setTimeout(function() {
                        window.location.href = '$login_url';
                    }, 3000);
                });
            </script>";
            exit;

        } else {
            wp_die("Lien invalide ou expiré.");
        }
    }
}
add_action('template_redirect', 'validate_user_account');


function block_unverified_users($user, $password) {
    if (is_wp_error($user)) {
        return $user;
    }

    $status = get_user_meta($user->ID, 'user_status', true);
    if ($status != 1) {
        return new WP_Error('account_not_verified', "Votre compte n'a pas encore été validé. Vérifiez vos emails.");
    }

    return $user;
}
add_filter('wp_authenticate_user', 'block_unverified_users', 10, 2);

/**
 * 
 * Adding User Status in admin > ACCOUNT
 * 
 */

 // Ajouter une colonne "Statut" dans l'administration des utilisateurs
function add_user_status_column($columns) {
    $columns['user_status'] = 'Statut';
    return $columns;
}
add_filter('manage_users_columns', 'add_user_status_column');

// Remplir la colonne avec le statut de l'utilisateur
function show_user_status_column_content($value, $column_name, $user_id) {
    if ($column_name == 'user_status') {
        $status = get_user_meta($user_id, 'user_status', true);
        return $status == 1 ? '<span style="color:green;">Validé</span>' : '<span style="color:red;">Non validé</span>';
    }
    return $value;
}
add_filter('manage_users_custom_column', 'show_user_status_column_content', 10, 3);


/**
 * 
 * Delete color options in admin > user > abonné
 * 
 */

// Supprimer les options inutiles pour les users abonnés dans leur profil admin
function remove_admin_profile_fields($user) {
    if (!current_user_can('edit_users')) { // Appliqué uniquement aux abonnés et non aux admins
        remove_action('admin_color_scheme_picker', 'admin_color_scheme_picker'); // Supprime le choix des couleurs
    }
}
add_action('admin_head', 'remove_admin_profile_fields');


/**
 * 
 * test email
*/
//wp_mail('tonemail@example.com', 'Test WordPress', 'Si tu reçois cet email, WordPress peut envoyer des emails.');







