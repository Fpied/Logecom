import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        url: String,
    };

    connect() {
        this.element.addEventListener(
            'autocomplete:pre-connect',
            this.onPreConnect
        );
    }

    disconnect() {
        this.element.removeEventListener(
            'autocomplete:pre-connect',
            this.onPreConnect
        );
    }

    onPreConnect = (event) => {
        event.detail.options.create = (input, callback) => {
            const data = new FormData();
            data.append('nom', input);

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
