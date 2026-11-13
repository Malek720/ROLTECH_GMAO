# Models for the GMAO catalog (OC-CA1100 real, all others DEMO)

OC-CA1100 comes from the real manual (`docs/oc-ca1100-data.md`, `data_status = confirmed`).
Every other model below is MOCKUP data invented for demonstration (`data_status = demo`). It must never be presented as RolTech data. Demo part numbers use the pattern `DEMO-<MODEL>-<MODULE>-<nn>` (e.g. `DEMO-HLS-M1-01`). Do not copy OC-CA1100 part numbers or its 15 kg limit to demo models.

## Models
| code | name (FR) | family | data_status | max_load_kg |
|---|---|---|---|---|
| OC-CA1100 | Convoyeur aérien OC-CA1100 | Convoyeur aérien (crochets) | confirmed | 15 per hook |
| HLS-CA1100 | Système logistique de bobines de câble | Logistique bobines | demo | 60 |
| HLS-PXL-CA1100 | Système logistique de gros faisceaux de câbles | Logistique faisceaux | demo | 80 |
| HLS-PXL-CA1100-01 | Système logistique de caisses | Logistique caisses | demo | 25 |
| PPXXL-CA1100 | Convoyeur de ligne de peinture | Ligne de peinture | demo | 20 |
| HP-2000X | Presse hydraulique HP-2000X | Presse | demo | empty |
| AirMax-300 | Compresseur d'air AirMax-300 | Utilitaire | demo | empty |

## Conveyor demo models (4): shared module structure
Modules M1..M7 (same codes in each model). Parts are (description, qty). Part numbers: `DEMO-<MODEL>-<MODULE>-<nn>`.
- M1 Groupe de renvoi 180°: carter (1), moyeu (1), disque supérieur (1), disque inférieur (1), roulement (2), arbre (1)
- M2 Groupe de renvoi 90°: same parts as M1
- M3 Groupe motorisation tendeur: moteur-réducteur (1), courroie (1), rail de tension (2), coulisse de rail (1)
- M4 Module poulie: poulie (1), roulement (2), plaque (2), arbre (1)
- M5 Tronçon: élément de traction (1), rail (1), support (1). Traction element: "corde" for HLS-CA1100 and HLS-PXL-CA1100-01, "chaîne" for HLS-PXL-CA1100 and PPXXL-CA1100.
- M6 Trolley: galet (2), flasque (2), axe (1)
- M7 Porteur de charge (per model):
  - HLS-CA1100: support de bobine (1), axe de bobine (1), verrou de sécurité (1)
  - HLS-PXL-CA1100: porte-faisceau (1), traverse renforcée (1), verrou de sécurité (1)
  - HLS-PXL-CA1100-01: porte-caisse (1), griffe de maintien (2)
  - PPXXL-CA1100: palonnier de peinture (1), crochet résistant à la chaleur (1)
- M8 Zone de séchage (PPXXL-CA1100 only): ventilateur (1), sonde de température (1), thermostat (1)

### Conveyor demo tasks (frequencies: weekly 7 d/168 h, monthly 30 d/720 h, quarterly 90 d/2160 h)
Same actions as the OC-CA1100 checklist, mapped to demo modules:
- M3 moteur-réducteur: vérifier le bruit (weekly), vérifier la température (weekly), vérifier le montage des boulons (monthly)
- M5 élément de traction: vérifier l'état (weekly), vérifier la tension (monthly)
- M1, M2, M4 roulements: vérifier le bruit (weekly), vérifier les boulons (monthly), graissage (quarterly)
- M5 rail et supports: contrôle général (monthly), vérifier les boulons (monthly)
- M6 trolley: vérifier le bruit (weekly), vérifier le montage (monthly), vérifier que les galets tournent librement (monthly)
- M7 porteur: vérifier l'état et la fixation du porteur (weekly)
- M8 (PPXXL only): vérifier le ventilateur (monthly), étalonner la sonde de température (quarterly)

### Conveyor troubleshooting
Reuse the four OC-CA1100 symptoms and causes/solutions (`docs/oc-ca1100-data.md`), marked demo for the demo models.

## HP-2000X Presse hydraulique (demo)
- M1 Groupe hydraulique: pompe (1), réservoir (1), filtre à huile (1), joint de piston (1)
- M2 Vérin principal: vérin (1), kit de joints (1), tige (1)
- M3 Armoire électrique: contacteur (2), relais thermique (1), capteur de pression (1)
- M4 Châssis et outillage: colonne (4), plateau (1), garde de sécurité (1)
Tasks (hour-based, machine runs 24h/day): contrôle de la pression (weekly, 168 h), vérifier la garde de sécurité (weekly), remplacer le filtre à huile (500 h), inspecter les joints du vérin (1000 h), remplacer l'huile hydraulique (2000 h), remplacer le kit de joints du vérin (4000 h).
Symptoms: perte de pression (causes: filtre colmaté, fuite interne; solutions: remplacer le filtre, contrôler le joint de piston), fuite d'huile (cause: joint usé; solution: remplacer le kit de joints), surchauffe de l'huile (causes: niveau bas, filtre colmaté; solutions: compléter le niveau, remplacer le filtre).

## AirMax-300 Compresseur d'air (demo)
- M1 Bloc de compression: bloc à vis (1), courroie (1), roulement (2)
- M2 Filtration: filtre à air (1), filtre à huile (1), séparateur air-huile (1)
- M3 Réservoir et purge: réservoir (1), purge automatique (1), soupape de sécurité (1)
- M4 Moteur et commande: moteur (1), pressostat (1), contacteur (1)
Tasks: contrôler la purge de condensat (weekly, 168 h), inspecter la courroie (1000 h), remplacer le filtre à air (1000 h), remplacer le filtre à huile (2000 h), tester la soupape de sécurité (quarterly, 2160 h), remplacer le séparateur air-huile (4000 h).
Symptoms: pression insuffisante (causes: filtre à air colmaté, fuite; solutions: remplacer le filtre, rechercher la fuite), surchauffe (causes: filtre à huile colmaté, ventilation obstruée; solutions: remplacer le filtre, dégager la ventilation), bruit anormal (cause: roulement usé; solution: remplacer le roulement).

## Demo fleet
Keep the 8 existing OC-CA1100 machines. Add 15 demo machines with realistic serial numbers (format `<PREFIX>-2024-0001`), mostly `en service`, plus two `en maintenance` and one `en panne`:
| model | count | sites |
|---|---|---|
| HLS-CA1100 | 3 | Sousse, Béja, Jendouba |
| HLS-PXL-CA1100 | 3 | Sousse, Sousse, Tunis |
| HLS-PXL-CA1100-01 | 3 | Béja, Jendouba, Tunis |
| PPXXL-CA1100 | 2 | Sousse, Jendouba |
| HP-2000X | 2 | Tunis, Béja |
| AirMax-300 | 2 | Sousse, Jendouba |