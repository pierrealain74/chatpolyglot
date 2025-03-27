@php
  $args = [
    'role'    => 'Subscriber', // Récupérer uniquement les abonnés
    'orderby' => 'display_name',
    'order'   => 'ASC',
    'number'  => 10 // Limiter à 10 utilisateurs (modifier selon besoin)
  ];
  $users = get_users($args);
@endphp

@if($users)
  <ul>
  @foreach($users as $user)
    <li>
        <strong>{{ $user->display_name }}</strong>

        @php
            // Récupérer les champs personnalisés de l'utilisateur
            $langue_maternelle = get_field('langue_maternelle', 'user_' . $user->ID);  // Langue maternelle
            $pratique_langue1 = get_field('souhaite_pratiquer_langue_1', 'user_' . $user->ID);  // Group field pour langue à pratiquer
            $pratique_langue2 = get_field('souhaite_pratiquer_langue_2', 'user_' . $user->ID);  // Group field pour langue à pratiquer
        @endphp

        @if($langue_maternelle || $pratique_langue1 || $pratique_langue2)
            <ul>
                @if($langue_maternelle)
                    <li>Langue maternelle : {{ esc_html($langue_maternelle) }}</li>
                @endif

                @if($pratique_langue1)
                    <!-- Accès direct aux sous-champs du groupe -->
                    <li>Souhaite pratiquer : {{ esc_html($pratique_langue1['langue'] ?? 'Inconnue') }} - Niveau : {{ esc_html($pratique_langue1['niveau'] ?? 'Non spécifié') }}</li>
                @else
                    <p>Aucune langue 1 à pratiquer renseignée.</p>
                @endif

                @if($pratique_langue2)
                    <!-- Accès direct aux sous-champs du groupe -->
                    <li>Souhaite pratiquer : {{ esc_html($pratique_langue2['langue'] ?? 'Inconnue') }} - Niveau : {{ esc_html($pratique_langue2['niveau'] ?? 'Non spécifié') }}</li>
                @else
                    <p>Aucune langue 2 à pratiquer renseignée.</p>
                @endif
            </ul>
        @else
            <p>Aucune langue renseignée.</p>
        @endif
    </li>
@endforeach

  </ul>
@else
  <p>Aucun utilisateur trouvé.</p>
@endif
