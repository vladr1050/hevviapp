<?php

declare(strict_types=1);

namespace App\Notification;

/**
 * Default MVP notification rules (Latvian). Loaded via app:notification:seed-rules.
 *
 * @phpstan-type RuleShape array{
 *     name: string,
 *     description: string|null,
 *     eventKey: string,
 *     recipientType: string,
 *     subjectTemplate: string,
 *     bodyTemplate: string,
 *     attachInvoicePdf: bool,
 *     sendOncePerOrder: bool
 * }
 */
final class NotificationRuleDefaults
{
    /**
     * @return list<RuleShape>
     */
    public static function all(): array
    {
        return [
            self::accepted(),
            self::assignedToSender(),
            self::assignedToCarrier(),
            self::inTransit(),
            self::delivered(),
            self::priceConfirmed(),
        ];
    }

    /**
     * @return RuleShape
     */
    private static function accepted(): array
    {
        $body = <<<'HTML'
<p>Labdien,</p>
<p>Jūsu pasūtījums ir pieņemts apstrādē Hevvi platformā.</p>
<p><strong>Pasūtījuma informācija:</strong></p>
<ul>
<li>Pasūtījuma ID: {{ORDER_ID}}</li>
<li>Maršruts: {{PICKUP_ADDRESS}} → {{DELIVERY_ADDRESS}}</li>
<li>Krava: {{CARGO_DESCRIPTION}}</li>
<li>Iekraušana: {{PICKUP_DATE}} {{PICKUP_TIME}}</li>
</ul>
<p>Mēs informēsim jūs par turpmākajiem soļiem.</p>
<p>Ar cieņu,<br>Hevvi Operāciju komanda<br>support@hevvi.app<br>www.hevvi.app</p>
HTML;

        return [
            'name' => 'Sender: pasūtījums pieņemts (ACCEPTED)',
            'description' => 'MVP: ORDER_STATUS_CHANGED_TO_ACCEPTED',
            'eventKey' => NotificationEventKey::ORDER_STATUS_CHANGED_TO_ACCEPTED,
            'recipientType' => NotificationRecipientType::SENDER,
            'subjectTemplate' => 'Pasūtījums pieņemts (ID {{ORDER_ID}})',
            'bodyTemplate' => $body,
            'attachInvoicePdf' => false,
            'sendOncePerOrder' => true,
        ];
    }

    /**
     * @return RuleShape
     */
    private static function assignedToSender(): array
    {
        $body = <<<'HTML'
<p>Labdien,</p>
<p>Jūsu pasūtījumam ir piešķirts pārvadātājs.</p>
<p><strong>Pasūtījuma ID:</strong> {{ORDER_ID}}<br>
<strong>Maršruts:</strong> {{PICKUP_ADDRESS}} → {{DELIVERY_ADDRESS}}</p>
<p><strong>Pārvadātājs:</strong></p>
<ul>
<li>Uzņēmums / nosaukums: {{CARRIER_NAME}}</li>
<li>Tālrunis: {{CARRIER_PHONE}}</li>
</ul>
<p>Ja ir jautājumi, sazinieties ar Hevvi komandu.</p>
<p>Ar cieņu,<br>Hevvi Operāciju komanda<br>support@hevvi.app<br>www.hevvi.app</p>
HTML;

        return [
            'name' => 'Sender: piešķirts pārvadātājs (ASSIGNED)',
            'description' => 'MVP: ORDER_STATUS_CHANGED_TO_ASSIGNED',
            'eventKey' => NotificationEventKey::ORDER_STATUS_CHANGED_TO_ASSIGNED,
            'recipientType' => NotificationRecipientType::SENDER,
            'subjectTemplate' => 'Pārvadātājs piešķirts (ID {{ORDER_ID}})',
            'bodyTemplate' => $body,
            'attachInvoicePdf' => false,
            'sendOncePerOrder' => true,
        ];
    }

    /**
     * @return RuleShape
     */
    private static function assignedToCarrier(): array
    {
        $body = <<<'HTML'
<p>Labdien,</p>
<p>Jums ir piešķirts jauns pārvadājuma uzdevums Hevvi platformā 🚛</p>
<p><strong>Pasūtījuma informācija:</strong></p>
<ul>
<li>Pasūtījuma ID: {{ORDER_ID}}</li>
<li>Maršruts: {{PICKUP_ADDRESS}} → {{DELIVERY_ADDRESS}}</li>
<li>Krava: {{CARGO_DESCRIPTION}}</li>
</ul>
<p><strong>Iekraušanas informācija:</strong></p>
<ul>
<li>Datums: {{PICKUP_DATE}}</li>
<li>Adrese: {{PICKUP_ADDRESS}}</li>
<li>Kontaktpersona: {{PICKUP_CONTACT}}</li>
</ul>
<p><strong>Klienta informācija:</strong></p>
<ul>
<li>Uzņēmums / persona: {{CLIENT_NAME}}</li>
<li>Tālrunis: {{CLIENT_PHONE}}</li>
</ul>
<p>Lūdzam:</p>
<ul>
<li>Ierasties norādītajā laikā</li>
<li>Pārbaudīt kravas stāvokli un atbilstību</li>
<li>Nodrošināt dokumentu parakstīšanu</li>
</ul>
<p>Ja rodas aizkavēšanās vai problēmas, nekavējoties informējiet Hevvi operāciju komandu.</p>
<p>Ar cieņu,<br>Hevvi Operāciju komanda<br>support@hevvi.app<br>www.hevvi.app</p>
HTML;

        return [
            'name' => 'Carrier: jauns pasūtījums',
            'description' => 'MVP: ORDER_ASSIGNED_TO_CARRIER',
            'eventKey' => NotificationEventKey::ORDER_ASSIGNED_TO_CARRIER,
            'recipientType' => NotificationRecipientType::CARRIER,
            'subjectTemplate' => 'Jauns Hevvi pasūtījums ID {{ORDER_ID}}',
            'bodyTemplate' => $body,
            'attachInvoicePdf' => false,
            'sendOncePerOrder' => true,
        ];
    }

    /**
     * @return RuleShape
     */
    private static function inTransit(): array
    {
        $body = <<<'HTML'
<p>Labdien,</p>
<p>Saskaņā ar sistēmas datiem krava ir atzīmēta kā paņemta un atrodas ceļā uz piegādes vietu.</p>
<p><strong>Statuss:</strong><br>✔ Krava paņemta<br>🚛 Ceļā uz galamērķi</p>
<p><strong>Piegādes informācija:</strong></p>
<ul>
<li>Piegādes adrese: {{DELIVERY_ADDRESS}}</li>
<li>Plānotais piegādes laiks (ETA): {{ETA}}</li>
</ul>
<p>Lūdzam informēt par jebkādām novirzēm no grafika, nodrošināt savlaicīgu piegādi un sagatavot saņēmēja parakstu (POD).</p>
<p>Ar cieņu,<br>Hevvi Operāciju komanda<br>support@hevvi.app<br>www.hevvi.app</p>
HTML;

        return [
            'name' => 'Sender: krava ceļā (IN_TRANSIT)',
            'description' => 'MVP: ORDER_STATUS_CHANGED_TO_IN_TRANSIT',
            'eventKey' => NotificationEventKey::ORDER_STATUS_CHANGED_TO_IN_TRANSIT,
            'recipientType' => NotificationRecipientType::SENDER,
            'subjectTemplate' => 'Statusa atjauninājums – Krava paņemta (ID {{ORDER_ID}})',
            'bodyTemplate' => $body,
            'attachInvoicePdf' => false,
            'sendOncePerOrder' => true,
        ];
    }

    /**
     * @return RuleShape
     */
    private static function delivered(): array
    {
        $body = <<<'HTML'
<p>Labdien,</p>
<p>Saskaņā ar sistēmas informāciju piegāde ir veiksmīgi pabeigta ✅</p>
<p><strong>Piegādes informācija:</strong></p>
<ul>
<li>Adrese: {{DELIVERY_ADDRESS}}</li>
<li>Datums: {{DELIVERY_DATE}}</li>
<li>Laiks: {{DELIVERY_TIME}}</li>
<li>Saņēmējs: {{RECEIVER_NAME}}</li>
</ul>
<p>Lūdzam augšupielādēt parakstītu pavadzīmi (POD) un papildu dokumentus (ja attiecināms). Savlaicīga dokumentu iesniegšana nodrošina ātrāku norēķinu apstrādi.</p>
<p>Paldies par sadarbību ar Hevvi.</p>
<p>Ar cieņu,<br>Hevvi Operāciju komanda<br>support@hevvi.app<br>www.hevvi.app</p>
HTML;

        return [
            'name' => 'Sender: piegāde pabeigta',
            'description' => 'MVP: ORDER_STATUS_CHANGED_TO_DELIVERED',
            'eventKey' => NotificationEventKey::ORDER_STATUS_CHANGED_TO_DELIVERED,
            'recipientType' => NotificationRecipientType::SENDER,
            'subjectTemplate' => 'Piegāde pabeigta (ID {{ORDER_ID}})',
            'bodyTemplate' => $body,
            'attachInvoicePdf' => false,
            'sendOncePerOrder' => true,
        ];
    }

    /**
     * @return RuleShape
     */
    private static function priceConfirmed(): array
    {
        $body = <<<'HTML'
<p>Labdien,</p>
<p>Paldies, ka apstiprinājāt pārvadājuma cenu.</p>
<p>Pielikumā pievienots rēķins par pasūtījumu {{ORDER_ID}}.</p>
<p><strong>Pasūtījuma informācija:</strong></p>
<ul>
<li>Pasūtījuma ID: {{ORDER_ID}}</li>
<li>Maršruts: {{PICKUP_ADDRESS}} → {{DELIVERY_ADDRESS}}</li>
<li>Krava: {{CARGO_DESCRIPTION}}</li>
</ul>
<p><strong>Rēķina informācija:</strong></p>
<ul>
<li>Rēķina numurs: {{INVOICE_NUMBER}}</li>
<li>Datums: {{INVOICE_DATE}}</li>
<li>Summa: {{TOTAL_AMOUNT}} {{CURRENCY}}</li>
<li>Apmaksas termiņš: {{PAYMENT_DUE_DATE}}</li>
</ul>
<p>Ja rodas jautājumi, sazinieties ar Hevvi komandu.</p>
<p>Ar cieņu,<br>Hevvi Operāciju komanda<br>support@hevvi.app<br>www.hevvi.app</p>
HTML;

        return [
            'name' => 'Sender: rēķins pēc cenas apstiprināšanas',
            'description' => 'MVP: ORDER_PRICE_CONFIRMED (PDF pielikums)',
            'eventKey' => NotificationEventKey::ORDER_PRICE_CONFIRMED,
            'recipientType' => NotificationRecipientType::SENDER,
            'subjectTemplate' => 'Rēķins par pārvadājumu – pasūtījums {{ORDER_ID}}',
            'bodyTemplate' => $body,
            'attachInvoicePdf' => true,
            'sendOncePerOrder' => true,
        ];
    }
}
