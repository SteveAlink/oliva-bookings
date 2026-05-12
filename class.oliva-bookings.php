<?php
/**
 * oliva-bookings - Bookings plugin for WonderCMS.
 * Prepared by Steve Alink for Oliva Solutions
 *
 * Adds simple no-payment reservations for Oliva Events dates.
 */

class OlivaBookings
{
    private $Wcms;
    private $translations = [];
    private $bookingProcessed = false;
    private $notice = '';
    private $noticeType = 'success';

    public function __construct($Wcms)
    {
        $this->Wcms = $Wcms;
        $this->loadTranslations();
        $this->populateDefaultValues();
    }

    private function loadTranslations()
    {
        $adminLang = $this->Wcms->get('config', 'adminLang');

        $map = [
            'en' => 'en_US',
            'nl' => 'nl_NL',
            'es' => 'es_ES'
        ];

        $langCode = $map[$adminLang] ?? 'en_US';
        $file = __DIR__ . '/languages/' . $langCode . '.ini';

        if (file_exists($file)) {
            $this->translations = parse_ini_file($file);
        } else {
            $this->translations = parse_ini_file(__DIR__ . '/languages/en_US.ini');
        }
    }

    private function t($key)
    {
        return $this->translations[$key] ?? '[[' . $key . ']]';
    }

    private function cleanText($value)
    {
        return trim(strip_tags((string) $value));
    }

    private function getDefaultVisitorLanguage()
    {
        $siteLang = $this->Wcms->get('config', 'siteLang');

        $map = [
            'en' => 'en_US',
            'nl' => 'nl_NL',
            'es' => 'es_ES'
        ];

        return $map[$siteLang] ?? 'en_US';
    }

    public function populateDefaultValues()
    {
        $defaults = [
            'olivaBookingsTitle' => $this->t('defaultBookingsTitle'),
            'olivaBookingsDefaultSpots' => '10',
            'olivaBookingsSpotOverrides' => '',
            'olivaBookingsVisitorLanguage' => $this->getDefaultVisitorLanguage(),
            'olivaBookingsPlacementMode' => 'placeholder',
            'olivaBookingsData' => '[]'
        ];

        foreach ($defaults as $key => $value) {
            $current = $this->Wcms->get('config', $key);

            if (empty($current) || is_object($current)) {
                $this->Wcms->set('config', $key, $value);
            }
        }
    }

    private function hasOlivaEvents()
    {
        if (class_exists('OlivaEvents')) {
            return true;
        }

        return file_exists(__DIR__ . '/../oliva-events/oliva-events.php')
            || file_exists(__DIR__ . '/../oliva-events/class.oliva-events.php');
    }

    public function getBookingsTitle()
    {
        return $this->Wcms->get('config', 'olivaBookingsTitle');
    }

    public function getDefaultSpots()
    {
        $spots = (int) $this->Wcms->get('config', 'olivaBookingsDefaultSpots');

        return $spots > 0 ? $spots : 10;
    }

    public function getSpotOverrides()
    {
        return $this->Wcms->get('config', 'olivaBookingsSpotOverrides');
    }

    public function getVisitorLanguage()
    {
        return $this->Wcms->get('config', 'olivaBookingsVisitorLanguage');
    }

    public function getPlacementMode()
    {
        $mode = $this->Wcms->get('config', 'olivaBookingsPlacementMode');

        if ($mode !== 'footer' && $mode !== 'placeholder') {
            return 'placeholder';
        }

        return $mode;
    }

    private function getVisitorTranslations()
    {
        $lang = $this->getVisitorLanguage();
        $file = __DIR__ . '/languages/' . $lang . '.ini';

        if (file_exists($file)) {
            return parse_ini_file($file);
        }

        return parse_ini_file(__DIR__ . '/languages/en_US.ini');
    }

    private function getMonths()
    {
        return [
            'en_US' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
            'nl_NL' => ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'],
            'es_ES' => ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre']
        ];
    }

    private function formatDate($date)
    {
        $timestamp = strtotime($date);

        if (!$timestamp) {
            return $date;
        }

        $lang = $this->getVisitorLanguage();
        $months = $this->getMonths();
        $monthNumber = (int) date('n', $timestamp);
        $monthName = $months[$lang][$monthNumber - 1] ?? $months['en_US'][$monthNumber - 1];

        return date('j', $timestamp) . ' ' . $monthName . ' ' . date('Y', $timestamp);
    }

    private function getMonthHeading($date)
    {
        $timestamp = strtotime($date);

        if (!$timestamp) {
            return '';
        }

        $lang = $this->getVisitorLanguage();
        $months = $this->getMonths();
        $monthNumber = (int) date('n', $timestamp);
        $monthName = $months[$lang][$monthNumber - 1] ?? $months['en_US'][$monthNumber - 1];

        return ucfirst($monthName) . ' ' . date('Y', $timestamp);
    }

    private function parseSpotOverrides()
    {
        $raw = $this->getSpotOverrides();
        $items = preg_split('/[\r\n,]+/', $raw);
        $overrides = [];

        foreach ($items as $item) {
            $item = trim($item);

            if ($item === '') {
                continue;
            }

            $parts = explode('|', $item, 2);
            $date = trim($parts[0]);
            $spots = isset($parts[1]) ? (int) trim($parts[1]) : 0;

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && $spots > 0) {
                $overrides[$date] = $spots;
            }
        }

        return $overrides;
    }

    private function parseEvents()
    {
        $raw = $this->Wcms->get('config', 'olivaEventsUnavailableDates');
        $items = preg_split('/[\r\n,]+/', (string) $raw);
        $events = [];
        $today = date('Y-m-d');
        $hidePastDates = $this->Wcms->get('config', 'olivaEventsHidePastDates') === 'yes';
        $spotOverrides = $this->parseSpotOverrides();

        foreach ($items as $item) {
            $item = trim($item);

            if ($item === '') {
                continue;
            }

            $parts = explode('|', $item, 2);
            $date = trim($parts[0]);
            $description = isset($parts[1]) ? trim($parts[1]) : '';

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }

            if ($hidePastDates && $date < $today) {
                continue;
            }

            $events[$date] = [
                'date' => $date,
                'description' => $description,
                'spots' => $spotOverrides[$date] ?? $this->getDefaultSpots()
            ];
        }

        ksort($events);

        return array_values($events);
    }

    private function groupEventsByMonth($events)
    {
        $grouped = [];

        foreach ($events as $event) {
            $timestamp = strtotime($event['date']);

            if (!$timestamp) {
                continue;
            }

            $key = date('Y-m', $timestamp);

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'heading' => $this->getMonthHeading($event['date']),
                    'events' => []
                ];
            }

            $grouped[$key]['events'][] = $event;
        }

        return $grouped;
    }

    private function getBookings()
    {
        $raw = $this->Wcms->get('config', 'olivaBookingsData');
        $bookings = json_decode((string) $raw, true);

        return is_array($bookings) ? $bookings : [];
    }

    private function saveBookings($bookings)
    {
        $this->Wcms->set('config', 'olivaBookingsData', json_encode(array_values($bookings)));
    }

    private function getBookedSpots($date)
    {
        $total = 0;

        foreach ($this->getBookings() as $booking) {
            if (($booking['date'] ?? '') === $date) {
                $total += (int) ($booking['spots'] ?? 1);
            }
        }

        return $total;
    }

    private function findEventByDate($date)
    {
        foreach ($this->parseEvents() as $event) {
            if ($event['date'] === $date) {
                return $event;
            }
        }

        return null;
    }

    private function processBookingRequest()
    {
        if ($this->bookingProcessed) {
            return;
        }

        $this->bookingProcessed = true;

        if (($_POST['oliva_bookings_action'] ?? '') !== 'reserve') {
            return;
        }

        $visitorTranslations = $this->getVisitorTranslations();
        $date = $this->cleanText($_POST['oliva_bookings_date'] ?? '');
        $name = $this->cleanText($_POST['oliva_bookings_name'] ?? '');
        $email = $this->cleanText($_POST['oliva_bookings_email'] ?? '');
        $spots = (int) ($_POST['oliva_bookings_spots'] ?? 1);
        $event = $this->findEventByDate($date);

        if (!$event || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $spots < 1) {
            $this->noticeType = 'error';
            $this->notice = $visitorTranslations['bookingError'] ?? 'Please check your booking details.';
            return;
        }

        $available = max(0, (int) $event['spots'] - $this->getBookedSpots($date));

        if ($spots > $available) {
            $this->noticeType = 'error';
            $this->notice = $visitorTranslations['notEnoughSpots'] ?? 'There are not enough spots available.';
            return;
        }

        $bookings = $this->getBookings();
        $bookings[] = [
            'date' => $date,
            'name' => $name,
            'email' => $email,
            'spots' => $spots,
            'created' => date('c')
        ];

        $this->saveBookings($bookings);
        $this->noticeType = 'success';
        $this->notice = $visitorTranslations['bookingSuccess'] ?? 'Your booking has been saved.';
    }

    private function createInput($doc, $name, $value)
    {
        $input = $doc->createElement('input');
        $input->setAttribute('type', 'text');
        $input->setAttribute('name', $name);
        $input->setAttribute('class', 'editText');
        $input->setAttribute('style', 'width: 100%; border: 1px solid #ccc;');
        $input->setAttribute('value', $value);

        return $input;
    }

    private function createTextarea($doc, $name, $value)
    {
        $textarea = $doc->createElement('textarea');
        $textarea->setAttribute('name', $name);
        $textarea->setAttribute('class', 'editText');
        $textarea->setAttribute('style', 'width: 100%; border: 1px solid #ccc;');
        $textarea->setAttribute('rows', '6');
        $textarea->nodeValue = $value;

        return $textarea;
    }

    private function createLabel($doc, $text)
    {
        return $doc->createElement('label', $text);
    }

    private function createSelect($doc, $name, $options, $currentValue)
    {
        $select = $doc->createElement('select');
        $select->setAttribute('name', $name);
        $select->setAttribute('class', 'editText');
        $select->setAttribute('style', 'width: 100%; border: 1px solid #ccc;');

        foreach ($options as $value => $label) {
            $option = $doc->createElement('option', $label);
            $option->setAttribute('value', $value);

            if ($currentValue === $value) {
                $option->setAttribute('selected', 'selected');
            }

            $select->appendChild($option);
        }

        return $select;
    }

    public function alterAdmin(array $args): array
    {
        $this->loadTranslations();
        $this->populateDefaultValues();

        $doc = new DOMDocument();
        @$doc->loadHTML(mb_convert_encoding($args[0], 'HTML-ENTITIES', 'UTF-8'));

        $currentPage = $doc->getElementById('currentPage');

        if (!$currentPage) {
            return $args;
        }

        $menuList = $currentPage
            ->parentNode
            ->parentNode
            ->childNodes
            ->item(1);

        $menuItem = $doc->createElement('li');
        $menuItem->setAttribute('class', 'nav-item');

        $menuItemA = $doc->createElement('a');
        $menuItemA->setAttribute('href', '#olivaBookingsSettings');
        $menuItemA->setAttribute('aria-controls', 'olivaBookingsSettings');
        $menuItemA->setAttribute('role', 'tab');
        $menuItemA->setAttribute('data-toggle', 'tab');
        $menuItemA->setAttribute('class', 'nav-link');
        $menuItemA->nodeValue = $this->t('OlivaBookings');

        $menuItem->appendChild($menuItemA);
        $menuList->appendChild($menuItem);

        $wrapper = $doc->createElement('div');
        $wrapper->setAttribute('role', 'tabpanel');
        $wrapper->setAttribute('class', 'tab-pane');
        $wrapper->setAttribute('id', 'olivaBookingsSettings');

        $form = $doc->createElement('form');
        $form->setAttribute('method', 'post');
        $form->setAttribute('action', '');

        $title = $doc->createElement('p');
        $title->setAttribute('class', 'subTitle');
        $title->nodeValue = $this->t('headingBookingsSettings');
        $form->appendChild($title);

        $form->appendChild($this->createLabel($doc, $this->t('labelBookingsTitle')));
        $form->appendChild($this->createInput($doc, 'oliva_bookings_title', $this->getBookingsTitle()));

        $form->appendChild($this->createLabel($doc, $this->t('labelPlacementMode')));
        $form->appendChild($this->createSelect($doc, 'oliva_bookings_placement_mode', [
            'footer' => $this->t('optionPlacementFooter'),
            'placeholder' => $this->t('optionPlacementPlaceholder')
        ], $this->getPlacementMode()));

        $placeholderHelp = $doc->createElement('p', $this->t('helpPlacementMode'));
        $placeholderHelp->setAttribute('class', 'small text-muted');
        $form->appendChild($placeholderHelp);

        $form->appendChild($this->createLabel($doc, $this->t('labelDefaultSpots')));
        $form->appendChild($this->createInput($doc, 'oliva_bookings_default_spots', (string) $this->getDefaultSpots()));

        $form->appendChild($this->createLabel($doc, $this->t('labelSpotOverrides')));
        $form->appendChild($this->createTextarea($doc, 'oliva_bookings_spot_overrides', $this->getSpotOverrides()));

        $help = $doc->createElement('p', $this->t('helpSpotOverrides'));
        $help->setAttribute('class', 'small text-muted');
        $form->appendChild($help);

        $form->appendChild($this->createLabel($doc, $this->t('labelVisitorLanguage')));
        $form->appendChild($this->createSelect($doc, 'oliva_bookings_visitor_language', [
            'en_US' => 'en_US',
            'nl_NL' => 'nl_NL',
            'es_ES' => 'es_ES'
        ], $this->getVisitorLanguage()));

        $saveButton = $doc->createElement('button');
        $saveButton->setAttribute('type', 'submit');
        $saveButton->setAttribute('name', 'saveOlivaBookingsSettings');
        $saveButton->setAttribute('class', 'wbtn wbtn-info');
        $saveButton->nodeValue = $this->t('saveButton');

        $form->appendChild($saveButton);

        if (!$this->hasOlivaEvents()) {
            $dependency = $doc->createElement('p', $this->t('dependencyMissing'));
            $dependency->setAttribute('class', 'subTitle');
            $form->appendChild($dependency);
        } else {

            $bookings = array_reverse($this->getBookings());
            $bookingsTitle = $doc->createElement('p');
            $bookingsTitle->setAttribute('class', 'subTitle');
            $bookingsTitle->nodeValue = $this->t('headingBookingsList');
            $form->appendChild($bookingsTitle);

            if (empty($bookings)) {
                $noBookings = $doc->createElement('p');
                $noBookings->setAttribute('class', 'change');
                $noBookings->nodeValue = $this->t('emptyBookingsMessage');
                $form->appendChild($noBookings);
            } else {
                $list = $doc->createElement('ul');
                foreach ($bookings as $booking) {
                    $line = ($booking['date'] ?? '') . ' - ' . ($booking['name'] ?? '') . ' - ' . ($booking['email'] ?? '') . ' - ' . ($booking['spots'] ?? 1);
                    $listItem = $doc->createElement('li');
                    $listItem->setAttribute('class', 'wbtn wbtn-info');
                    $listItem->nodeValue = $line;
                    $list->appendChild($listItem);
                }
                $form->appendChild($list);
            }
        }

        $wrapper->appendChild($form);
        $currentPage->parentNode->appendChild($wrapper);

        $args[0] = preg_replace(
            '~<(?:!DOCTYPE|/?(?:html|body))[^>]*>\s*~i',
            '',
            $doc->saveHTML()
        );

        return $args;
    }

    public function handleSettings(array $args): array
    {
        if (!$this->Wcms->loggedIn) {
            return $args;
        }

        if (isset($_POST['saveOlivaBookingsSettings'])) {
            $placementMode = $this->cleanText($_POST['oliva_bookings_placement_mode'] ?? 'placeholder');

            if ($placementMode !== 'footer' && $placementMode !== 'placeholder') {
                $placementMode = 'placeholder';
            }

            $defaultSpots = (int) ($_POST['oliva_bookings_default_spots'] ?? 10);

            if ($defaultSpots < 1) {
                $defaultSpots = 10;
            }

            $visitorLanguage = $this->cleanText($_POST['oliva_bookings_visitor_language'] ?? 'en_US');

            if (!in_array($visitorLanguage, ['en_US', 'nl_NL', 'es_ES'], true)) {
                $visitorLanguage = 'en_US';
            }

            $this->Wcms->set('config', 'olivaBookingsTitle', $this->cleanText($_POST['oliva_bookings_title'] ?? $this->t('defaultBookingsTitle')));
            $this->Wcms->set('config', 'olivaBookingsPlacementMode', $placementMode);
            $this->Wcms->set('config', 'olivaBookingsDefaultSpots', (string) $defaultSpots);
            $this->Wcms->set('config', 'olivaBookingsSpotOverrides', $this->cleanText($_POST['oliva_bookings_spot_overrides'] ?? ''));
            $this->Wcms->set('config', 'olivaBookingsVisitorLanguage', $visitorLanguage);
        }

        return $this->alterAdmin($args);
    }

    private function buildBookingsHtml()
    {
        $this->processBookingRequest();
        $visitorTranslations = $this->getVisitorTranslations();
        $title = htmlspecialchars($this->getBookingsTitle(), ENT_QUOTES, 'UTF-8');
        $html = PHP_EOL;

        $html .= '<section id="oliva-bookings" class="oliva-bookings">' . PHP_EOL;
        $html .= '  <h2>' . $title . '</h2>' . PHP_EOL;

        if (!$this->hasOlivaEvents()) {
            $html .= '  <p class="oliva-bookings-empty">' . htmlspecialchars($visitorTranslations['dependencyMissingFrontend'] ?? 'Oliva Events is required.', ENT_QUOTES, 'UTF-8') . '</p>' . PHP_EOL;
            $html .= '</section>' . PHP_EOL;
            return $html;
        }

        if ($this->notice !== '') {
            $html .= '  <p class="oliva-bookings-notice oliva-bookings-notice-' . $this->noticeType . '">' . htmlspecialchars($this->notice, ENT_QUOTES, 'UTF-8') . '</p>' . PHP_EOL;
        }

        $events = $this->parseEvents();
        $groupedEvents = $this->groupEventsByMonth($events);

        if (empty($groupedEvents)) {
            $html .= '  <p class="oliva-bookings-empty">' . htmlspecialchars($visitorTranslations['emptyEventsMessage'] ?? 'No events available for booking.', ENT_QUOTES, 'UTF-8') . '</p>' . PHP_EOL;
        } else {
            foreach ($groupedEvents as $month) {
                $html .= '  <div class="oliva-bookings-month">' . PHP_EOL;
                $html .= '    <h3>' . htmlspecialchars($month['heading'], ENT_QUOTES, 'UTF-8') . '</h3>' . PHP_EOL;
                $html .= '    <ul class="oliva-bookings-list">' . PHP_EOL;

                foreach ($month['events'] as $event) {
                    $safeDate = htmlspecialchars($event['date'], ENT_QUOTES, 'UTF-8');
                    $formattedDate = htmlspecialchars($this->formatDate($event['date']), ENT_QUOTES, 'UTF-8');
                    $description = htmlspecialchars($event['description'], ENT_QUOTES, 'UTF-8');
                    $available = max(0, (int) $event['spots'] - $this->getBookedSpots($event['date']));
                    $spotLabel = $available === 1 ? ($visitorTranslations['spotAvailable'] ?? 'spot available') : ($visitorTranslations['spotsAvailable'] ?? 'spots available');

                    $html .= '      <li class="oliva-bookings-event" data-date="' . $safeDate . '">' . PHP_EOL;
                    $html .= '        <div class="oliva-bookings-event-main">' . PHP_EOL;
                    $html .= '          <span class="oliva-bookings-date-value">' . $formattedDate . '</span>' . PHP_EOL;

                    if ($description !== '') {
                        $html .= '          <span class="oliva-bookings-date-description">' . $description . '</span>' . PHP_EOL;
                    }

                    $html .= '          <span class="oliva-bookings-spots">' . $available . ' ' . htmlspecialchars($spotLabel, ENT_QUOTES, 'UTF-8') . '</span>' . PHP_EOL;
                    $html .= '        </div>' . PHP_EOL;

                    if ($available > 0) {
                        $html .= '        <form method="post" class="oliva-bookings-form">' . PHP_EOL;
                        $html .= '          <input type="hidden" name="oliva_bookings_action" value="reserve">' . PHP_EOL;
                        $html .= '          <input type="hidden" name="oliva_bookings_date" value="' . $safeDate . '">' . PHP_EOL;
                        $html .= '          <input type="text" name="oliva_bookings_name" placeholder="' . htmlspecialchars($visitorTranslations['namePlaceholder'] ?? 'Name', ENT_QUOTES, 'UTF-8') . '" required>' . PHP_EOL;
                        $html .= '          <input type="email" name="oliva_bookings_email" placeholder="' . htmlspecialchars($visitorTranslations['emailPlaceholder'] ?? 'Email', ENT_QUOTES, 'UTF-8') . '" required>' . PHP_EOL;
                        $html .= '          <input type="number" name="oliva_bookings_spots" value="1" min="1" max="' . $available . '" required>' . PHP_EOL;
                        $html .= '          <button type="submit">' . htmlspecialchars($visitorTranslations['reserveButton'] ?? 'Reserve', ENT_QUOTES, 'UTF-8') . '</button>' . PHP_EOL;
                        $html .= '        </form>' . PHP_EOL;
                    } else {
                        $html .= '        <span class="oliva-bookings-full">' . htmlspecialchars($visitorTranslations['fullyBooked'] ?? 'Fully booked', ENT_QUOTES, 'UTF-8') . '</span>' . PHP_EOL;
                    }

                    $html .= '      </li>' . PHP_EOL;
                }

                $html .= '    </ul>' . PHP_EOL;
                $html .= '  </div>' . PHP_EOL;
            }
        }

        $html .= '</section>' . PHP_EOL;

        return $html;
    }

    public function renderBookings(array $args): array
    {
        if ($this->getPlacementMode() !== 'footer') {
            return $args;
        }

        $args[0] .= $this->buildBookingsHtml();

        return $args;
    }

    public function replacePlaceholder(array $args): array
    {
        if ($this->getPlacementMode() !== 'placeholder') {
            return $args;
        }

        if (!isset($args[1]) || $args[1] !== 'content') {
            return $args;
        }

        if (strpos($args[0], '{{oliva-bookings}}') === false) {
            return $args;
        }

        $args[0] = str_replace('{{oliva-bookings}}', $this->buildBookingsHtml(), $args[0]);

        return $args;
    }
}
