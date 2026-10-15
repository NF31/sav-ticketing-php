<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ClientRepository;

/**
 * Applique un evenement customer.golden_record.updated recu de
 * kbeauty-customer-mdm : ferme le risque de divergence identifie (un client
 * cree via un import de retour - ImportRetourKbeauty - ne voyait jamais ses
 * changements de nom/email cote boutique repercutes ici).
 *
 * Idempotent : RabbitMQ livre "au moins une fois", un meme message peut
 * arriver deux fois - appliquer deux fois la meme identite est sans effet.
 *
 * Limite connue : pour un client jamais lie (source_user_id encore NULL,
 * cree via un import de retour avant cette integration), la seule cle de
 * recherche disponible est l'email transporte par l'evenement. Si ce
 * tout premier evenement change le nom ET l'email en meme temps, la
 * recherche par (nouvel) email ne retrouve pas la ligne existante (qui
 * porte encore l'ancien email) : un nouveau client est cree a tort, en
 * plus de l'ancien qui reste orphelin. Une fois source_user_id lie (a la
 * premiere mise a jour ou l'email n'a pas change en meme temps), les
 * mises a jour suivantes n'ont plus ce probleme.
 */
final class MettreAJourClientGolden
{
    public function __construct(private readonly ClientRepository $clients)
    {
    }

    /**
     * @return bool false si l'email cible est deja pris par un autre client
     *              (conflit a examiner manuellement, message rejete)
     */
    public function appliquer(object $goldenRecord): bool
    {
        [$prenom, $nom] = ImportRetourKbeauty::separerNom($goldenRecord->name);

        $id = $this->clients->findIdParSourceUserId($goldenRecord->sourceUserId)
            // Premiere mise a jour recue pour un client cree avant cette
            // integration (import de retour, source_user_id encore NULL) :
            // retrouve par email, backfill source_user_id au passage.
            ?? $this->clients->findIdParEmail($goldenRecord->email);

        if ($id === null) {
            // Le MDM a connaissance d'un client que le SAV n'a jamais vu
            // (aucun retour soumis encore) : on cree sa fiche a l'avance,
            // un futur retour la retrouvera par email.
            $this->clients->create($nom, $prenom, $goldenRecord->email, null, null, null, null, $goldenRecord->sourceUserId);

            return true;
        }

        return $this->clients->mettreAJourIdentite($id, $prenom, $nom, $goldenRecord->email, $goldenRecord->sourceUserId);
    }
}
