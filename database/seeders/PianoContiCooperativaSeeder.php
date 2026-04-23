<?php

namespace Database\Seeders;

use App\Models\ContoContabile;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Precarica il piano dei conti standard per cooperative italiane.
 *
 * Struttura gerarchica a 4 livelli (Classe → Mastro → Conto → Sottoconto)
 * basata sulla codifica CEE (artt. 2424/2425 c.c.) + adattamenti cooperativi
 * (capitale sociale a soci, ristorni, prestito sociale).
 *
 * Idempotente: usa firstOrCreate su (tenant_id, codice).
 *
 * Uso:
 *   (new PianoContiCooperativaSeeder)->perTenant($tenant);
 *
 * Oppure via TenantObserver::created() alla creazione di un nuovo tenant coop.
 */
class PianoContiCooperativaSeeder extends Seeder
{
    /**
     * Piano dei conti template.
     *
     * Ogni voce: [codice, descrizione, natura, tipo_bilancio, classe_bilancio_ce, note_opzionali...]
     * Il livello, il segno_naturale, il parent_id e il flag movimentabile
     * sono derivati automaticamente dal codice.
     *
     * Note tecniche:
     *  - Solo le voci con livello 4 (3 punti nel codice) sono movimentabili.
     *  - I fondi ammortamento hanno natura=attivo ma segno_naturale=avere
     *    (sono poste rettificative dell'attivo).
     */
    public const PIANO_CONTI = [
        // ═══════════════════════════════════════════════════════════════════
        //  CLASSE 1 — ATTIVITÀ (Stato Patrimoniale Attivo)
        // ═══════════════════════════════════════════════════════════════════
        ['1',            'ATTIVITÀ',                                 'attivo', 'sp_attivo', null],
        ['1.10',         'Immobilizzazioni immateriali',              'attivo', 'sp_attivo', 'B.I'],
        ['1.10.05',      'Costi di impianto e ampliamento',           'attivo', 'sp_attivo', 'B.I.1'],
        ['1.10.05.001',  'Costi di impianto e ampliamento',           'attivo', 'sp_attivo', 'B.I.1'],
        ['1.10.15',      'Software',                                  'attivo', 'sp_attivo', 'B.I.3'],
        ['1.10.15.001',  'Software applicativo',                      'attivo', 'sp_attivo', 'B.I.3'],
        ['1.10.20',      'Avviamento',                                'attivo', 'sp_attivo', 'B.I.5'],
        ['1.10.20.001',  'Avviamento',                                'attivo', 'sp_attivo', 'B.I.5'],

        ['1.20',         'Immobilizzazioni materiali',                'attivo', 'sp_attivo', 'B.II'],
        ['1.20.05',      'Terreni e fabbricati',                      'attivo', 'sp_attivo', 'B.II.1'],
        ['1.20.05.001',  'Terreni',                                   'attivo', 'sp_attivo', 'B.II.1'],
        ['1.20.05.002',  'Fabbricati',                                'attivo', 'sp_attivo', 'B.II.1'],
        ['1.20.10',      'Impianti e macchinari',                     'attivo', 'sp_attivo', 'B.II.2'],
        ['1.20.10.001',  'Impianti generici',                         'attivo', 'sp_attivo', 'B.II.2'],
        ['1.20.10.002',  'Macchinari',                                'attivo', 'sp_attivo', 'B.II.2'],
        ['1.20.15',      'Attrezzature industriali e commerciali',    'attivo', 'sp_attivo', 'B.II.3'],
        ['1.20.15.001',  'Attrezzature',                              'attivo', 'sp_attivo', 'B.II.3'],
        ['1.20.20',      'Altri beni',                                'attivo', 'sp_attivo', 'B.II.4'],
        ['1.20.20.001',  'Mobili e arredi',                           'attivo', 'sp_attivo', 'B.II.4'],
        ['1.20.20.002',  'Macchine ufficio elettroniche',             'attivo', 'sp_attivo', 'B.II.4'],
        ['1.20.20.003',  'Automezzi',                                 'attivo', 'sp_attivo', 'B.II.4'],

        // Fondi ammortamento (poste rettificative — segno avere forzato)
        ['1.25',         'Fondi ammortamento immobilizzazioni',       'attivo', 'sp_attivo', 'B.II', 'avere'],
        ['1.25.05',      'F.do amm.to fabbricati',                    'attivo', 'sp_attivo', 'B.II.1', 'avere'],
        ['1.25.05.001',  'F.do amm.to fabbricati',                    'attivo', 'sp_attivo', 'B.II.1', 'avere'],
        ['1.25.10',      'F.do amm.to impianti e macchinari',         'attivo', 'sp_attivo', 'B.II.2', 'avere'],
        ['1.25.10.001',  'F.do amm.to impianti e macchinari',         'attivo', 'sp_attivo', 'B.II.2', 'avere'],
        ['1.25.15',      'F.do amm.to attrezzature',                  'attivo', 'sp_attivo', 'B.II.3', 'avere'],
        ['1.25.15.001',  'F.do amm.to attrezzature',                  'attivo', 'sp_attivo', 'B.II.3', 'avere'],
        ['1.25.20',      'F.do amm.to altri beni',                    'attivo', 'sp_attivo', 'B.II.4', 'avere'],
        ['1.25.20.001',  'F.do amm.to mobili e arredi',               'attivo', 'sp_attivo', 'B.II.4', 'avere'],
        ['1.25.20.002',  'F.do amm.to macchine ufficio',              'attivo', 'sp_attivo', 'B.II.4', 'avere'],
        ['1.25.20.003',  'F.do amm.to automezzi',                     'attivo', 'sp_attivo', 'B.II.4', 'avere'],

        ['1.40',         'Rimanenze',                                 'attivo', 'sp_attivo', 'C.I'],
        ['1.40.05',      'Magazzino',                                 'attivo', 'sp_attivo', 'C.I'],
        ['1.40.05.001',  'Rimanenze materie prime',                   'attivo', 'sp_attivo', 'C.I.1'],
        ['1.40.05.002',  'Rimanenze prodotti finiti',                 'attivo', 'sp_attivo', 'C.I.4'],
        ['1.40.05.003',  'Rimanenze merci',                           'attivo', 'sp_attivo', 'C.I.4'],

        ['1.50',         'Crediti v/clienti',                         'attivo', 'sp_attivo', 'C.II.1'],
        ['1.50.05',      'Clienti',                                   'attivo', 'sp_attivo', 'C.II.1'],
        ['1.50.05.001',  'Clienti Italia',                            'attivo', 'sp_attivo', 'C.II.1'],
        ['1.50.05.002',  'Clienti UE',                                'attivo', 'sp_attivo', 'C.II.1'],
        ['1.50.05.003',  'Clienti Extra-UE',                          'attivo', 'sp_attivo', 'C.II.1'],
        ['1.50.10',      'Note credito da emettere',                  'attivo', 'sp_attivo', 'C.II.1'],
        ['1.50.10.001',  'Note credito da emettere',                  'attivo', 'sp_attivo', 'C.II.1'],

        ['1.55',         'Crediti diversi',                           'attivo', 'sp_attivo', 'C.II.5-bis'],
        ['1.55.05',      'Crediti v/erario',                          'attivo', 'sp_attivo', 'C.II.5-bis'],
        ['1.55.05.001',  'Erario c/IVA a credito',                    'attivo', 'sp_attivo', 'C.II.5-bis'],
        ['1.55.05.002',  'Erario c/acconti IRES',                     'attivo', 'sp_attivo', 'C.II.5-bis'],
        ['1.55.10',      'Crediti v/enti previdenziali',              'attivo', 'sp_attivo', 'C.II.5-bis'],
        ['1.55.10.001',  'INPS c/crediti',                            'attivo', 'sp_attivo', 'C.II.5-bis'],
        ['1.55.20',      'Crediti v/soci',                            'attivo', 'sp_attivo', 'A'],
        ['1.55.20.001',  'Crediti v/soci c/sottoscrizioni',           'attivo', 'sp_attivo', 'A'],

        ['1.60',         'Disponibilità liquide',                     'attivo', 'sp_attivo', 'C.IV'],
        ['1.60.05',      'Depositi bancari e postali',                'attivo', 'sp_attivo', 'C.IV.1'],
        ['1.60.05.001',  'Banca c/c',                                 'attivo', 'sp_attivo', 'C.IV.1'],
        ['1.60.10',      'Assegni',                                   'attivo', 'sp_attivo', 'C.IV.2'],
        ['1.60.10.001',  'Assegni',                                   'attivo', 'sp_attivo', 'C.IV.2'],
        ['1.60.15',      'Denaro e valori in cassa',                  'attivo', 'sp_attivo', 'C.IV.3'],
        ['1.60.15.001',  'Cassa contanti',                            'attivo', 'sp_attivo', 'C.IV.3'],

        ['1.70',         'Ratei e risconti attivi',                   'attivo', 'sp_attivo', 'D'],
        ['1.70.05',      'Ratei attivi',                              'attivo', 'sp_attivo', 'D'],
        ['1.70.05.001',  'Ratei attivi',                              'attivo', 'sp_attivo', 'D'],
        ['1.70.10',      'Risconti attivi',                           'attivo', 'sp_attivo', 'D'],
        ['1.70.10.001',  'Risconti attivi',                           'attivo', 'sp_attivo', 'D'],

        // ═══════════════════════════════════════════════════════════════════
        //  CLASSE 2 — PASSIVITÀ
        // ═══════════════════════════════════════════════════════════════════
        ['2',            'PASSIVITÀ',                                 'passivo', 'sp_passivo', null],

        ['2.10',         'Fondi rischi e oneri',                      'passivo', 'sp_passivo', 'B'],
        ['2.10.05',      'Fondi rischi',                              'passivo', 'sp_passivo', 'B'],
        ['2.10.05.001',  'Fondo rischi generico',                     'passivo', 'sp_passivo', 'B.4'],

        ['2.20',         'TFR',                                       'passivo', 'sp_passivo', 'C'],
        ['2.20.05',      'Trattamento fine rapporto',                 'passivo', 'sp_passivo', 'C'],
        ['2.20.05.001',  'Fondo TFR',                                 'passivo', 'sp_passivo', 'C'],

        ['2.30',         'Debiti v/banche',                           'passivo', 'sp_passivo', 'D.4'],
        ['2.30.05',      'Mutui passivi',                             'passivo', 'sp_passivo', 'D.4'],
        ['2.30.05.001',  'Mutui passivi',                             'passivo', 'sp_passivo', 'D.4'],
        ['2.30.10',      'Banca c/c passivi',                         'passivo', 'sp_passivo', 'D.4'],
        ['2.30.10.001',  'Banca c/c passivo',                         'passivo', 'sp_passivo', 'D.4'],

        ['2.40',         'Debiti v/fornitori',                        'passivo', 'sp_passivo', 'D.7'],
        ['2.40.05',      'Fornitori',                                 'passivo', 'sp_passivo', 'D.7'],
        ['2.40.05.001',  'Fornitori Italia',                          'passivo', 'sp_passivo', 'D.7'],
        ['2.40.05.002',  'Fornitori UE',                              'passivo', 'sp_passivo', 'D.7'],
        ['2.40.05.003',  'Fornitori Extra-UE',                        'passivo', 'sp_passivo', 'D.7'],
        ['2.40.10',      'Fatture da ricevere',                       'passivo', 'sp_passivo', 'D.7'],
        ['2.40.10.001',  'Fatture da ricevere',                       'passivo', 'sp_passivo', 'D.7'],

        ['2.45',         'Debiti tributari',                          'passivo', 'sp_passivo', 'D.12'],
        ['2.45.05',      'IVA',                                       'passivo', 'sp_passivo', 'D.12'],
        ['2.45.05.001',  'Erario c/IVA (debito)',                     'passivo', 'sp_passivo', 'D.12'],
        ['2.45.05.002',  'IVA a debito',                              'passivo', 'sp_passivo', 'D.12'],
        ['2.45.10',      'Imposte dirette',                           'passivo', 'sp_passivo', 'D.12'],
        ['2.45.10.001',  'Erario c/IRES',                             'passivo', 'sp_passivo', 'D.12'],
        ['2.45.10.002',  'Erario c/IRAP',                             'passivo', 'sp_passivo', 'D.12'],
        ['2.45.15',      'Ritenute',                                  'passivo', 'sp_passivo', 'D.12'],
        ['2.45.15.001',  'Erario c/rit. lavoro dipendente',           'passivo', 'sp_passivo', 'D.12'],
        ['2.45.15.002',  'Erario c/rit. lavoro autonomo',             'passivo', 'sp_passivo', 'D.12'],

        ['2.50',         'Debiti v/enti previdenziali',               'passivo', 'sp_passivo', 'D.13'],
        ['2.50.05',      'INPS',                                      'passivo', 'sp_passivo', 'D.13'],
        ['2.50.05.001',  'INPS c/contributi',                         'passivo', 'sp_passivo', 'D.13'],
        ['2.50.10',      'INAIL',                                     'passivo', 'sp_passivo', 'D.13'],
        ['2.50.10.001',  'INAIL c/contributi',                        'passivo', 'sp_passivo', 'D.13'],

        ['2.55',         'Debiti v/personale',                        'passivo', 'sp_passivo', 'D.14'],
        ['2.55.05',      'Dipendenti',                                'passivo', 'sp_passivo', 'D.14'],
        ['2.55.05.001',  'Dipendenti c/retribuzioni',                 'passivo', 'sp_passivo', 'D.14'],

        // Conti specifici cooperative
        ['2.60',         'Debiti v/soci',                             'passivo', 'sp_passivo', 'D.3'],
        ['2.60.05',      'Prestito sociale',                          'passivo', 'sp_passivo', 'D.3'],
        ['2.60.05.001',  'Soci c/prestito sociale',                   'passivo', 'sp_passivo', 'D.3'],
        ['2.60.10',      'Ristorni',                                  'passivo', 'sp_passivo', 'D.14'],
        ['2.60.10.001',  'Soci c/ristorni',                           'passivo', 'sp_passivo', 'D.14'],
        ['2.60.15',      'Dividendi',                                 'passivo', 'sp_passivo', 'D.14'],
        ['2.60.15.001',  'Soci c/dividendi da pagare',                'passivo', 'sp_passivo', 'D.14'],

        ['2.70',         'Ratei e risconti passivi',                  'passivo', 'sp_passivo', 'E'],
        ['2.70.05',      'Ratei passivi',                             'passivo', 'sp_passivo', 'E'],
        ['2.70.05.001',  'Ratei passivi',                             'passivo', 'sp_passivo', 'E'],
        ['2.70.10',      'Risconti passivi',                          'passivo', 'sp_passivo', 'E'],
        ['2.70.10.001',  'Risconti passivi',                          'passivo', 'sp_passivo', 'E'],

        // ═══════════════════════════════════════════════════════════════════
        //  CLASSE 3 — PATRIMONIO NETTO
        // ═══════════════════════════════════════════════════════════════════
        ['3',            'PATRIMONIO NETTO',                          'patrimonio_netto', 'pn', 'A'],

        ['3.10',         'Capitale sociale',                          'patrimonio_netto', 'pn', 'A.I'],
        ['3.10.05',      'Capitale sociale cooperativa',              'patrimonio_netto', 'pn', 'A.I'],
        ['3.10.05.001',  'Capitale sociale soci cooperatori',         'patrimonio_netto', 'pn', 'A.I'],
        ['3.10.05.002',  'Capitale sociale soci sovventori',          'patrimonio_netto', 'pn', 'A.I'],

        ['3.20',         'Riserve',                                   'patrimonio_netto', 'pn', 'A.IV'],
        ['3.20.05',      'Riserve',                                   'patrimonio_netto', 'pn', 'A.IV'],
        ['3.20.05.001',  'Riserva legale indivisibile',               'patrimonio_netto', 'pn', 'A.IV'],
        ['3.20.05.002',  'Riserva statutaria',                        'patrimonio_netto', 'pn', 'A.VI'],
        ['3.20.05.003',  'Riserva straordinaria',                     'patrimonio_netto', 'pn', 'A.VI'],

        ['3.30',         'Utili/perdite portati a nuovo',             'patrimonio_netto', 'pn', 'A.VIII'],
        ['3.30.05',      'Risultati a nuovo',                         'patrimonio_netto', 'pn', 'A.VIII'],
        ['3.30.05.001',  'Utili portati a nuovo',                     'patrimonio_netto', 'pn', 'A.VIII'],
        ['3.30.05.002',  'Perdite portate a nuovo',                   'patrimonio_netto', 'pn', 'A.VIII'],

        ['3.40',         "Utile/perdita d'esercizio",                 'patrimonio_netto', 'pn', 'A.IX'],
        ['3.40.05',      "Risultato d'esercizio",                     'patrimonio_netto', 'pn', 'A.IX'],
        ['3.40.05.001',  "Utile d'esercizio",                         'patrimonio_netto', 'pn', 'A.IX'],
        ['3.40.05.002',  "Perdita d'esercizio",                       'patrimonio_netto', 'pn', 'A.IX'],

        // ═══════════════════════════════════════════════════════════════════
        //  CLASSE 4 — RICAVI (Conto Economico)
        // ═══════════════════════════════════════════════════════════════════
        ['4',            'RICAVI',                                    'ricavo', 'ce_valore_produzione', null],

        ['4.10',         'Ricavi delle vendite e prestazioni',        'ricavo', 'ce_valore_produzione', 'A.1'],
        ['4.10.05',      'Ricavi vendite',                            'ricavo', 'ce_valore_produzione', 'A.1'],
        ['4.10.05.001',  'Ricavi vendite merci',                      'ricavo', 'ce_valore_produzione', 'A.1'],
        ['4.10.05.002',  'Ricavi prestazioni di servizi',             'ricavo', 'ce_valore_produzione', 'A.1'],
        ['4.10.10',      'Ricavi attività mutualistica',              'ricavo', 'ce_valore_produzione', 'A.1'],
        ['4.10.10.001',  'Ricavi servizi ai soci',                    'ricavo', 'ce_valore_produzione', 'A.1'],

        ['4.40',         'Altri ricavi e proventi',                   'ricavo', 'ce_valore_produzione', 'A.5'],
        ['4.40.05',      'Contributi',                                'ricavo', 'ce_valore_produzione', 'A.5'],
        ['4.40.05.001',  'Contributi in conto esercizio',             'ricavo', 'ce_valore_produzione', 'A.5'],
        ['4.40.05.002',  'Contributi in conto capitale',              'ricavo', 'ce_valore_produzione', 'A.5'],
        ['4.40.10',      'Sopravvenienze attive',                     'ricavo', 'ce_valore_produzione', 'A.5'],
        ['4.40.10.001',  'Sopravvenienze attive',                     'ricavo', 'ce_valore_produzione', 'A.5'],
        ['4.40.15',      'Quote associative',                         'ricavo', 'ce_valore_produzione', 'A.5'],
        ['4.40.15.001',  'Quote associative istituzionali',           'ricavo', 'ce_valore_produzione', 'A.5'],

        ['4.50',         'Proventi finanziari',                       'ricavo', 'ce_proventi_oneri_finanziari', 'C.16'],
        ['4.50.05',      'Interessi attivi',                          'ricavo', 'ce_proventi_oneri_finanziari', 'C.16.d'],
        ['4.50.05.001',  'Interessi attivi bancari',                  'ricavo', 'ce_proventi_oneri_finanziari', 'C.16.d'],

        // ═══════════════════════════════════════════════════════════════════
        //  CLASSE 5 — COSTI (Conto Economico)
        // ═══════════════════════════════════════════════════════════════════
        ['5',            'COSTI',                                     'costo', 'ce_costi_produzione', null],

        ['5.10',         'Acquisti',                                  'costo', 'ce_costi_produzione', 'B.6'],
        ['5.10.05',      'Acquisti',                                  'costo', 'ce_costi_produzione', 'B.6'],
        ['5.10.05.001',  'Acquisti merci',                            'costo', 'ce_costi_produzione', 'B.6'],
        ['5.10.05.002',  'Acquisti materie prime',                    'costo', 'ce_costi_produzione', 'B.6'],
        ['5.10.05.003',  'Acquisti materiali di consumo',             'costo', 'ce_costi_produzione', 'B.6'],

        ['5.20',         'Servizi',                                   'costo', 'ce_costi_produzione', 'B.7'],
        ['5.20.05',      'Utenze',                                    'costo', 'ce_costi_produzione', 'B.7'],
        ['5.20.05.001',  'Energia elettrica',                         'costo', 'ce_costi_produzione', 'B.7'],
        ['5.20.05.002',  'Gas e riscaldamento',                       'costo', 'ce_costi_produzione', 'B.7'],
        ['5.20.05.003',  'Telefono e internet',                       'costo', 'ce_costi_produzione', 'B.7'],
        ['5.20.05.004',  'Acqua',                                     'costo', 'ce_costi_produzione', 'B.7'],
        ['5.20.10',      'Consulenze e compensi',                     'costo', 'ce_costi_produzione', 'B.7'],
        ['5.20.10.001',  'Consulenze professionali',                  'costo', 'ce_costi_produzione', 'B.7'],
        ['5.20.10.002',  'Spese legali e notarili',                   'costo', 'ce_costi_produzione', 'B.7'],
        ['5.20.10.003',  'Compensi amministratori',                   'costo', 'ce_costi_produzione', 'B.7'],
        ['5.20.15',      'Assicurazioni',                             'costo', 'ce_costi_produzione', 'B.7'],
        ['5.20.15.001',  'Premi assicurazione',                       'costo', 'ce_costi_produzione', 'B.7'],
        ['5.20.20',      'Pubblicità',                                'costo', 'ce_costi_produzione', 'B.7'],
        ['5.20.20.001',  'Pubblicità e marketing',                    'costo', 'ce_costi_produzione', 'B.7'],
        ['5.20.25',      'Trasporti',                                 'costo', 'ce_costi_produzione', 'B.7'],
        ['5.20.25.001',  'Trasporti su acquisti',                     'costo', 'ce_costi_produzione', 'B.7'],
        ['5.20.25.002',  'Trasporti su vendite',                      'costo', 'ce_costi_produzione', 'B.7'],
        ['5.20.30',      'Manutenzioni',                              'costo', 'ce_costi_produzione', 'B.7'],
        ['5.20.30.001',  'Manutenzioni e riparazioni',                'costo', 'ce_costi_produzione', 'B.7'],
        ['5.20.35',      'Altri servizi',                             'costo', 'ce_costi_produzione', 'B.7'],
        ['5.20.35.001',  'Cancelleria e stampati',                    'costo', 'ce_costi_produzione', 'B.7'],
        ['5.20.35.002',  'Postali e valori bollati',                  'costo', 'ce_costi_produzione', 'B.7'],
        ['5.20.35.003',  'Altri oneri di gestione',                   'costo', 'ce_costi_produzione', 'B.7'],

        ['5.30',         'Godimento beni di terzi',                   'costo', 'ce_costi_produzione', 'B.8'],
        ['5.30.05',      'Affitti',                                   'costo', 'ce_costi_produzione', 'B.8'],
        ['5.30.05.001',  'Fitti passivi',                             'costo', 'ce_costi_produzione', 'B.8'],
        ['5.30.10',      'Leasing e noleggi',                         'costo', 'ce_costi_produzione', 'B.8'],
        ['5.30.10.001',  'Canoni di leasing',                         'costo', 'ce_costi_produzione', 'B.8'],
        ['5.30.10.002',  'Noleggi',                                   'costo', 'ce_costi_produzione', 'B.8'],

        ['5.40',         'Personale',                                 'costo', 'ce_costi_produzione', 'B.9'],
        ['5.40.05',      'Salari e stipendi',                         'costo', 'ce_costi_produzione', 'B.9.a'],
        ['5.40.05.001',  'Salari e stipendi',                         'costo', 'ce_costi_produzione', 'B.9.a'],
        ['5.40.10',      'Oneri sociali',                             'costo', 'ce_costi_produzione', 'B.9.b'],
        ['5.40.10.001',  'Oneri sociali INPS',                        'costo', 'ce_costi_produzione', 'B.9.b'],
        ['5.40.10.002',  'Oneri sociali INAIL',                       'costo', 'ce_costi_produzione', 'B.9.b'],
        ['5.40.15',      'TFR',                                       'costo', 'ce_costi_produzione', 'B.9.c'],
        ['5.40.15.001',  'Accantonamento TFR',                        'costo', 'ce_costi_produzione', 'B.9.c'],

        ['5.50',         'Ammortamenti e svalutazioni',               'costo', 'ce_costi_produzione', 'B.10'],
        ['5.50.05',      'Ammortamenti immobilizzazioni materiali',   'costo', 'ce_costi_produzione', 'B.10.b'],
        ['5.50.05.001',  'Amm.to fabbricati',                         'costo', 'ce_costi_produzione', 'B.10.b'],
        ['5.50.05.002',  'Amm.to impianti e macchinari',              'costo', 'ce_costi_produzione', 'B.10.b'],
        ['5.50.05.003',  'Amm.to attrezzature',                       'costo', 'ce_costi_produzione', 'B.10.b'],
        ['5.50.05.004',  'Amm.to mobili e arredi',                    'costo', 'ce_costi_produzione', 'B.10.b'],
        ['5.50.05.005',  'Amm.to automezzi',                          'costo', 'ce_costi_produzione', 'B.10.b'],
        ['5.50.10',      'Ammortamenti immobilizzazioni immateriali', 'costo', 'ce_costi_produzione', 'B.10.a'],
        ['5.50.10.001',  'Amm.to software',                           'costo', 'ce_costi_produzione', 'B.10.a'],
        ['5.50.10.002',  'Amm.to costi di impianto',                  'costo', 'ce_costi_produzione', 'B.10.a'],

        ['5.80',         'Oneri diversi di gestione',                 'costo', 'ce_costi_produzione', 'B.14'],
        ['5.80.05',      'Imposte indirette',                         'costo', 'ce_costi_produzione', 'B.14'],
        ['5.80.05.001',  'Imposte di bollo',                          'costo', 'ce_costi_produzione', 'B.14'],
        ['5.80.05.002',  'Imposte ipotecarie e catastali',            'costo', 'ce_costi_produzione', 'B.14'],
        ['5.80.10',      'Sopravvenienze passive',                    'costo', 'ce_costi_produzione', 'B.14'],
        ['5.80.10.001',  'Sopravvenienze passive',                    'costo', 'ce_costi_produzione', 'B.14'],

        ['5.90',         'Oneri finanziari',                          'costo', 'ce_proventi_oneri_finanziari', 'C.17'],
        ['5.90.05',      'Interessi passivi',                         'costo', 'ce_proventi_oneri_finanziari', 'C.17'],
        ['5.90.05.001',  'Interessi passivi bancari',                 'costo', 'ce_proventi_oneri_finanziari', 'C.17'],
        ['5.90.05.002',  'Interessi passivi su mutui',                'costo', 'ce_proventi_oneri_finanziari', 'C.17'],

        ['5.95',         'Imposte sul reddito',                       'costo', 'ce_imposte', '20'],
        ['5.95.05',      'IRES',                                      'costo', 'ce_imposte', '20'],
        ['5.95.05.001',  'IRES corrente',                             'costo', 'ce_imposte', '20'],
        ['5.95.10',      'IRAP',                                      'costo', 'ce_imposte', '20'],
        ['5.95.10.001',  'IRAP corrente',                             'costo', 'ce_imposte', '20'],

        // ═══════════════════════════════════════════════════════════════════
        //  CLASSE 9 — CONTI DI CHIUSURA
        // ═══════════════════════════════════════════════════════════════════
        ['9',            'CONTI DI CHIUSURA',                         'transitorio', 'transitorio', null],
        ['9.00',         'Chiusura esercizio',                        'transitorio', 'transitorio', null],
        ['9.00.05',      'Conto Economico di chiusura',               'transitorio', 'transitorio', null],
        ['9.00.05.001',  'Conto Economico di chiusura',               'transitorio', 'transitorio', null],
        ['9.00.10',      'Bilancio di chiusura',                      'transitorio', 'transitorio', null],
        ['9.00.10.001',  'Stato Patrimoniale finale',                 'transitorio', 'transitorio', null],
    ];

    /**
     * Esegui il seeder per un tenant specifico.
     * Idempotente: se il conto già esiste su (tenant_id, codice), viene skippato.
     */
    public function perTenant(Tenant $tenant): void
    {
        // Prima passata: crea tutti i conti senza parent_id
        // (l'ordine dell'array garantisce che i padri vengano prima dei figli)
        $mapCodiceId = [];

        foreach (self::PIANO_CONTI as $voce) {
            [$codice, $descrizione, $natura, $tipoBilancio, $classeBilancio] = $voce;
            $segnoNaturaleOverride = $voce[5] ?? null;

            $livello     = ContoContabile::calcolaLivelloDaCodice($codice);
            $codicePadre = ContoContabile::calcolaCodicePadre($codice);
            $parentId    = $codicePadre !== null ? ($mapCodiceId[$codicePadre] ?? null) : null;

            $segnoNaturale = $segnoNaturaleOverride ?? $this->segnoDefaultPerNatura($natura);

            $conto = ContoContabile::withoutGlobalScope('tenant')->firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'codice'    => $codice,
                ],
                [
                    'tenant_id'          => $tenant->id,
                    'descrizione'        => $descrizione,
                    'parent_id'          => $parentId,
                    'livello'            => $livello,
                    'natura'             => $natura,
                    'segno_naturale'     => $segnoNaturale,
                    'classe_bilancio_ce' => $classeBilancio,
                    'tipo_bilancio'      => $tipoBilancio,
                    'movimentabile'      => $livello === 4,
                    'di_sistema'         => true,
                    'attivo'             => true,
                ],
            );

            $mapCodiceId[$codice] = $conto->id;
        }
    }

    /**
     * Segno naturale di default per natura del conto.
     *   Attivo/Costo → Dare
     *   Passivo/PN/Ricavo → Avere
     *   Transitorio/Conto d'ordine → Dare (neutro)
     */
    private function segnoDefaultPerNatura(string $natura): string
    {
        return match ($natura) {
            ContoContabile::NATURA_ATTIVO,
            ContoContabile::NATURA_COSTO,
            ContoContabile::NATURA_CONTO_ORDINE,
            ContoContabile::NATURA_TRANSITORIO  => ContoContabile::SEGNO_DARE,

            ContoContabile::NATURA_PASSIVO,
            ContoContabile::NATURA_PATRIMONIO_NETTO,
            ContoContabile::NATURA_RICAVO        => ContoContabile::SEGNO_AVERE,

            default => ContoContabile::SEGNO_DARE,
        };
    }

    /**
     * Esegue il seeder per tutti i tenant di tipo cooperativa.
     * Invocabile: php artisan db:seed --class=PianoContiCooperativaSeeder
     */
    public function run(): void
    {
        Tenant::query()
            ->where('organization_type', 'cooperative')
            ->each(function (Tenant $tenant) {
                $this->perTenant($tenant);
            });
    }
}
