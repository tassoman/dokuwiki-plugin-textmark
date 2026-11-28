<?php

/**
 * @author Tassoman
 * @license AGPL-3.0
 */

$lang['watermark_text']     = 'Testo da applicare come filigrana. Lascia vuoto per generare automaticamente "©{year} {wiki title}", usando l\'URL di base se non è impostato alcun titolo.';
$lang['font_file']          = 'File del carattere. Carica un file .ttf o .otf tramite il gestore media e inserisci il suo ID (es. <code>fonts:myfont.ttf</code>), oppure un percorso relativo al plugin (es. <code>fonts/default.ttf</code>). Lascia vuoto per usare il carattere incluso.';
$lang['font_size']          = 'Dimensione del carattere in percentuale (1-100) rispetto al lato più corto dell\'immagine, così la filigrana si adatta alle dimensioni della foto';
$lang['min_font_size']      = 'Dimensione minima del carattere in punti, applicata quando il calcolo percentuale risulterebbe inferiore (predefinito: 16)';
$lang['text_color']         = 'Codice colore esadecimale per il testo della filigrana (es. #FFFFFF)';
$lang['text_opacity']       = 'Percentuale di opacità del testo (0 = trasparente, 100 = coprente)';
$lang['position']           = 'Posizione della filigrana sull\'immagine';
$lang['angle']              = 'Angolo di rotazione del testo in gradi (es. 45 per diagonale)';
$lang['border']             = 'Disegna un bordo attorno al testo per mantenerlo leggibile su qualsiasi sfondo';
$lang['border_color']       = 'Codice colore esadecimale per il bordo del testo (es. #000000)';
$lang['border_width']       = 'Spessore del bordo in pixel (1-50)';
$lang['padding']            = 'Margine dai bordi dell\'immagine in pixel';
$lang['min_width']          = 'Larghezza minima dell\'immagine (in pixel) richiesta per applicare la filigrana';
$lang['exclude_namespaces'] = 'Elenco separato da virgole dei namespace media da escludere (es. wiki,public:icons)';
