# OC-CA1100 maintenance data (source: LEONI_2025v02.pdf, ROLTECH manual v1.0, 08/2019)

Model: OC-CA1100 overhead conveyor (convoyeur aérien). Client in this manual: LEONI.
Serial number is printed on the machine as "SERIAL #xxxx" and is required, with the model and part number, when ordering spare parts from ROLTECH.
Max load: 15 kg per hook. Hook heights: loading 1950 mm, transit/door 2500 mm, unloading 2000 mm.

## Safety rules that apply to every intervention
- Before any maintenance: cut pneumatic and electrical supply, switch off at main switch, lock out against restart.
- Maintenance only by qualified, trained staff. Electrical work only by a qualified electrician.
- Use only original ROLTECH parts or equivalents validated by ROLTECH.

## Modules (planche = drawing sheet) and parts
Columns: module | planche | part number | qty | description / type

### M1 Groupe de renvoi 180° (GR 180 1100) | OC1100-01
GR-180-100 1100 (1, housing) | TF-108-100 1100 (1) | PL-600-100 1100 (1) | TF-203-100 1100 (1) | PL-600-317 1100 (1) | DIN 628 SKF - SKF 7206 BE (2, bearing) | TF-103-100 1100 (1) | T2001 (1)

### M2 Groupe de renvoi 90° (GR 90 1100) | OC1100-02
GR-90-100 1100 (1, housing) | TF-108-100 1100 (1) | PL-450-100 1100 (1) | TF-203-100 1100 (1) | PL-450-308 1100 (1) | DIN 628 SKF - SKF 7206 BE (2, bearing) | TF-103-100 1100 (1) | T1001 (1)

### M3 Groupe Motorisation Tendeur (GM T 200 1100) | OC1100-03
GM-800 01 1100 (1, module poulie = M4) | RT-300 1100 (2, rail de tension) | V-Belt 1800 (1, belt) | TK-550 120 01 (1, coulisse de rail) | GM-700 02 1100 (1, module support moteur = M5)

### M4 Module poulie (GM-800 01 1100) | OC1100-04
GM-800-100 1100 (1) | TF-108-100 1100 (1) | PL-600-100 1100 (1) | TF-203-100 1100 (1) | DIN 628 SKF - SKF 7206 BE (2, bearing) | T3001 (1) | PL-600-317 1100 (1) | PR 450 B17 11 40 001 (1, pulley) | TF-213-100 1100 (1)

### M5 Module support moteur (GM-700 02 1100) | OC1100-05
GM-700-100-CM63 (1) | GM-700-200-CM63 (1) | TF-204-CM63 (1) | GM-700-400-UCF206 (1) | UCF206 (1, bearing unit) | GM-700-300-UCF206 (1) | MR-0.75-CM63-100 (1, motor-reducer 0.75) | TF-501-1100 (1, shaft) | PR 200 B17 11 40 (1, pulley)

### M6 Tronçon (section) | OC1100-06
T-200-1100 (trolley = M7) | Corde D14 (rope) | Croche 300 (hook) | R-1952 1100 (rail) | S-6001 (support)
Quantities are not given in the manual: leave qty empty.

### M7 Trolley (T-200-1100) | OC1100-07
T-200-6200 (2, wheel) | T-200-337 (2, plate) | T-200-3210 (1)

## Preventive checklist (manual section 8)
Components and actions come from the manual. FREQUENCY IS A PROPOSED DEFAULT, TO CONFIRM against the PDF (tick marks were not readable).

| Component (manual) | Action | Frequency (to confirm) |
|---|---|---|
| Moteur et réducteur | vérifier le bruit | weekly |
| Moteur et réducteur | vérifier la température | weekly |
| Moteur et réducteur | vérifier le montage des boulons | monthly |
| Corde | vérifier l'état | weekly |
| Corde | vérifier la tension | monthly |
| Roulements et paliers | vérifier le bruit | weekly |
| Roulements et paliers | vérifier le montage des boulons | monthly |
| Roulements et paliers | graissage des roulements | quarterly |
| Rail et supportage | contrôle général | monthly |
| Rail et supportage | vérifier le montage des boulons | monthly |
| Trolley | vérifier le bruit | weekly |
| Trolley | vérifier le montage | monthly |
| Trolley | vérifier que les galets tournent librement | monthly |

Mapping of checklist components to modules: Moteur et réducteur -> M5 (MR-0.75-CM63-100); Corde -> M6 (Corde D14); Roulements et paliers -> M1, M2, M4 (SKF 7206 BE) and M5 (UCF206); Rail et supportage -> M6 (R-1952 1100, S-6001) and M3 (RT-300, TK-550); Trolley -> M7.

## Rope tension procedure (task checklist for "vérifier la tension")
1. Stop and lock out the conveyor. 2. Locate the rope junction device. 3. Loosen fixings. 4. Increase tension gradually. 5. Check tension with a cable tensiometer (recommended, kgf or daN) or by deflection method. 6. Re-tighten to the recommended torque. 7. Check no excessive sag at rest and that trolleys move correctly. 8. Test run (manual trigger test, then reduced speed). Recheck after temperature changes or natural rope stretching. Any slip, abnormal noise or excess wear: stop immediately and do a corrective intervention.

## Troubleshooting knowledge base (manual section 7)
| Symptom | Causes | Solutions |
|---|---|---|
| Conveyor does not start or motor stops frequently | 1) motor overloaded 2) motor draws too much current | 1) check conveyor overload 2) check temperature/circuit breaker, replace if needed |
| Cracking noise at the return discs (disques de renvoi) | 1) defective bearing 2) defective housing/palier | 1) replace bearing 2) replace palier |
| Motor or reducer overheating | 1) conveyor overloaded 2) low voltage | 1) check capacity, reduce load to recommended value 2) have an electrician check |
| Rope stops but motor runs | 1) overload 2) rope not tensioned 3) rope blocked by an obstacle | 1) reduce load 2) tension the rope 3) remove obstacle |