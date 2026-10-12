# Contrats de donnees RabbitMQ

Copie des schemas JSON Schema definis dans `kbeauty-shop/contracts/`
(source de verite : kbeauty-shop est le producteur des evenements). Les
deux depots sont separes, il n'y a pas de lien entre eux : la copie est
verifiee par `kbeauty-shop/contracts/check-sync.sh ../sav-ticketing-php`.

## Fichiers

- `rabbitmq/return-request-submitted.schema.json` : evenement
  `return_request.submitted` (une demande de retour soumise sur la
  boutique), consomme par `bin/consommer-retours-kbeauty.php` et valide par
  `App\Integration\Kbeauty\ContratRetourSoumis` avant tout traitement.
