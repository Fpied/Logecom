// Contrôleur Stimulus pour le champ autocomplete de sélection de catégorie.
// Permet de créer une nouvelle catégorie à la volée si elle n'existe pas
// encore dans la liste, en l'envoyant en AJAX vers le contrôleur Symfony
// (route de création de catégorie), sans recharger la page.
import { Controller } from '@hotwired/stimulus'; // importation Javascript qui va cherche la classe Controller dans la bibliothèque Stimulus 
// Le framework JS utilisé par Symfony

// Déclare une propriété url , de type texte , que Stimulus va récupérer automatiquement depuis le HTML et rendre disponible dans le JS
export default class extends Controller {
    static values = {
        url: String,
    };

    // connect() active l'écoute d'un événement spécifique dès que le contrôleur est branché sur l'élément
    connect() {
        this.element.addEventListener(
            'autocomplete:pre-connect',
            this.onPreConnect
        );
    }

    // disconnect() nettoie proprement cette écoute quand l'élément disparaît, pour éviter le gaspillage de ressources
    disconnect() {
        this.element.removeEventListener(
            'autocomplete:pre-connect',
            this.onPreConnect
        );
    }

    // onPreConnect() est la fonction qui sera appelée lors de l'événement 'autocomplete:pre-connect'
    onPreConnect = (event) => {
        event.detail.options.create = (input, callback) => {
            const data = new FormData();
            data.append('nom', input);
            const form = this.element.closest('form');
            const csrfToken = form.querySelector(
                'input[name="_categorie_csrf"]'
            ).value;
            data.append('_token', csrfToken);

            // Le fetch sert à envoyer le nom de la nouvelle catégorie au serveur Symfony ( en POST, avec le token CSRF),
            // pour que la catégorie soit créée en baase de données , sans recharger la page.
            fetch(this.urlValue, {
                method: 'POST',
                body: data,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Impossible de créer la catégorie.');
                    }

                    return response.json();
                })
                .then((categorie) => {
                    callback({
                        value: categorie.id,
                        text: categorie.nom,
                    });
                })
                .catch(() => {
                    callback();
                });
        };
    };

}
