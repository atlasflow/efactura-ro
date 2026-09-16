# efactura-ro

A framework-free PHP kernel for Romanian e-invoicing: the EN 16931 document model, a CIUS-RO UBL 2.1 writer and reader, layered validation that predicts what ANAF's validator will say, and a PSR-18 client for every endpoint of ANAF's e-Factura API.

It computes nothing. You supply every amount as a decimal string, the kernel verifies the arithmetic to the cent, writes the XML, and talks to ANAF. Money is `brick/math` inside; a `float` is refused at the door.

```php
use AtlasFlow\EFacturaRo\Ubl\UblWriter;
use AtlasFlow\EFacturaRo\Validation\LocalValidator;

$xml    = (new UblWriter)->write($document);          // CIUS-RO UBL 2.1
$result = (new LocalValidator)->validate($document);  // XSD + BR-* rules, offline
$result = $anaf->validateDocument($document);         // ANAF's own validator, no credentials
```

Two release tiers, deliberately. Everything that ANAF's public validator can prove — the document model, the writer and reader, the local rules, `validate`, `render`, `verifySignature` — is tagged `v0.1.0` and runs green against ANAF on every fixture in CI. The OAuth client and the authenticated endpoints (`upload`, `status`, `messages`, `download`) are built from the Ministry of Finance's published API pages and covered by recorded-response tests, but **have not yet been run against a live ANAF session**. `tests/Live/UploadTest.php` is the gate that promotes them; it needs a certificate holder with SPV rights.

The Laravel bridge — persisted authorisations, submissions, the inbound mailbox, jobs and events — is the separate package [`atlasflow/efactura-ro-laravel`](https://github.com/atlasflow/efactura-ro-laravel).

## Requirements

- PHP 8.4 with `ext-dom`, `ext-libxml`, `ext-zip`
- A PSR-18 HTTP client and PSR-17 factories for anything that talks to ANAF (Guzzle, Symfony HttpClient, Laravel's `Http` through the bridge, …)

## Install

```bash
composer require atlasflow/efactura-ro
```

## Building a document

Every class in `Document\` is `final readonly`. Amounts are `Amount::of('12.50')`; identifiers are `Cui::of('RO12345674')` (prefix stripped, check digit verified), `Cnp::of(...)`, or `ForeignIdentifier::of('DE123456789', 'DE')`.

```php
use AtlasFlow\EFacturaRo\Document\{Address, AllowanceCharge, Document, DocumentType, Line, LineVat, Party, PaymentMeans, TaxSubtotal, Totals};
use AtlasFlow\EFacturaRo\Support\{Amount, Cui};
use AtlasFlow\EFacturaRo\Vat\VatCategory;
use DateTimeImmutable;

$seller = new Party(
    name: 'Pepiniera Verde SRL',
    address: new Address('Str. Florilor 10', 'Cluj-Napoca', 'RO', 'RO-CJ', '400001'),
    taxIdentifier: Cui::of('RO12345674'),
    vatRegistered: true,
    registrationNumber: 'J12/345/2015',
);

$buyer = new Party(
    name: 'Grădina Albastră SRL',
    address: new Address('Bd. Unirii 5', 'SECTOR3', 'RO', 'RO-B', '030167'),   // Bucharest: RO-B + SECTOR1…6
    taxIdentifier: Cui::of('40000000'),
    vatRegistered: true,
);

$s21 = LineVat::standard('21');

$invoice = new Document(
    type: DocumentType::INVOICE,
    number: 'PV-2026-000123',
    issueDate: new DateTimeImmutable('2026-09-10'),
    currency: 'RON',
    seller: $seller,
    buyer: $buyer,
    lines: [
        new Line('1', 'Buxus sempervirens 30-40 cm', Amount::of('100'), Amount::of('12.50'), Amount::of('1250.00'), $s21),
        new Line('2', 'Transport', Amount::of('1'), Amount::of('150.00'), Amount::of('150.00'), $s21),
    ],
    taxSubtotals: [
        new TaxSubtotal(VatCategory::S, Amount::of('21'), Amount::of('1350.00'), Amount::of('283.50')),
    ],
    totals: new Totals(
        lineExtensionAmount: Amount::of('1400.00'),
        taxExclusiveAmount: Amount::of('1350.00'),
        taxAmount: Amount::of('283.50'),
        taxInclusiveAmount: Amount::of('1633.50'),
        payableAmount: Amount::of('1633.50'),
        allowanceTotal: Amount::of('50.00'),
    ),
    dueDate: new DateTimeImmutable('2026-10-10'),
    paymentMeans: [PaymentMeans::bankTransfer('RO49AAAA1B31007593840000')],
    allowancesCharges: [AllowanceCharge::allowance('50.00', 'Discount volum', '95', $s21)],
);
```

A credit note is `DocumentType::CREDIT_NOTE` with `precedingDocuments: [new PrecedingDocument('PV-2026-000123', $date)]`; it is written as a UBL `CreditNote`. A consumer is `Party::consumer('Ion Popescu', $address, Cnp::of('…'))` — or without the CNP, in which case the writer emits ANAF's placeholder `0000000000000` — and routes the upload to `/uploadb2c`. A foreign-currency document needs `taxCurrency: 'RON'` and `taxTotalInTaxCurrency` (BR-RO-030).

`tests/Fixtures/Documents.php` holds eight worked documents — standard, prepayment and rounding, credit note, intra-EU supply in EUR, reverse charge, B2C with and without CNP, VAT-exempt seller — all accepted by ANAF's validator.

### VAT codes

VAT goes on the wire as category + rate (+ VATEX code and reason for the exempt-like categories). If your application persists a neutral treatment instead, `RomanianVatMapping` turns it into the codes, with the legal basis for each pairing in its docblock:

```php
use AtlasFlow\EFacturaRo\Vat\{RomanianVatMapping, VatTreatment};

$k  = LineVat::fromCode(RomanianVatMapping::for(VatTreatment::INTRA_EU_SUPPLY));      // K, VATEX-EU-IC
$ae = LineVat::fromCode(RomanianVatMapping::for(VatTreatment::REVERSE_CHARGE));       // AE, VATEX-EU-AE
$e  = LineVat::fromCode(RomanianVatMapping::for(VatTreatment::EXEMPT, 'VATEX-EU-F')); // E, the article is yours to name
```

## Writing and reading UBL

```php
use AtlasFlow\EFacturaRo\Ubl\{UblReader, UblWriter};

$xml      = (new UblWriter)->write($invoice);
$document = (new UblReader)->read($xml);   // tolerant: unknown elements ignored, bad CUIs kept as ForeignIdentifier
```

How parties are identified on the wire, verified against ANAF's validator on 2026-09-16:

| Party | `PartyTaxScheme` | `PartyLegalEntity/CompanyID` |
|---|---|---|
| VAT-registered | `RO` + CUI, scheme `VAT` (BT-31/48) | bare CUI (BT-30/47) |
| not registered | bare CUI, scheme `TAX` (BT-32) | bare CUI |
| consumer | none | CNP or `0000000000000` |
| foreign, VAT-registered | prefixed VAT id, scheme `VAT` | the same id |

The seller's trade register number goes in `CompanyLegalForm`; the buyer's is not written (UBL-CR-244 refuses it). A delivery address needs a country subdivision even outside Romania (BR-RO-211).

## Validating

Three layers, in the order ANAF finds things:

```php
use AtlasFlow\EFacturaRo\Validation\{LocalValidator, RemoteValidator, Validator};

$local  = new LocalValidator;                       // XSD → arithmetic, identity, EN 16931, CIUS-RO rules
$remote = new RemoteValidator($anafClient);          // POST validare/{FACT1|FCN}, no credentials
$result = (new Validator($local, $remote))->full($invoice);

foreach ($result->errors as $error) {
    echo $error->source->value, ' ', $error->code, ' at ', $error->path, ': ', $error->message, PHP_EOL;
    // arithmetic BR-CO-13 at totals.taxExclusiveAmount: BT-106 − BT-107 + BT-108 is 1350.00, BT-109 says 1400.00.
    // anaf BR-RO-110 at /Invoice/cac:AccountingSupplierParty/cac:Party/cac:PostalAddress: …
}
```

Each local rule is one class under `Validation/Rules/`, named for the schematron id it mirrors, with the rule text and ANAF's tolerance in its docblock. Sums are exact to the cent; BR-CO-17, BR-S-08 and BR-S-09 accept less than one unit of drift, as MF's schematron does. `LINE-NET-AMOUNT` (quantity × price ± line allowances = BT-131) is stricter than ANAF, which never checks it; pass your own `rules:` list to `LocalValidator` to drop it. `PartiesIdentified` predicts ANAF's post-schematron `ERRIdentif` layer, which MF's own sample invoice fails.

`validate()` at ANAF has no `extern=DA`, so a buyer with no Romanian CUI passes the schematron and then fails the identity layer with `nu a fost identificat cui cumparator`; only an upload can prove that last step for a foreign buyer.

## Talking to ANAF

`AnafClient` is stateless. Every authenticated call takes the bearer token as an argument; nothing is cached, nothing is persisted.

```php
use AtlasFlow\EFacturaRo\Anaf\{AnafClient, Environment, PollSchedule, UploadOptions};

$anaf = new AnafClient($psr18Client, $psr17Factory, $psr17Factory, Environment::TEST);

$receipt = $anaf->uploadDocument($invoice, $token);            // POST /upload or /uploadb2c, standard UBL|CN, extern=DA when implied
$status  = $anaf->status($receipt->index, $token);             // PROCESSING | OK | NOK | REFUSED (+ downloadId once terminal)

sleep(PollSchedule::nextDelay($attempt));                      // 30 s, 1, 2, 5, 15, 30 min, then hourly — under 100/message/day

$bundle = $anaf->download($status->downloadId, $token);        // the ZIP unpacked: payloadXml + MF's signatureXml
$anaf->verifySignature($bundle->payloadXml, $bundle->signatureXml);  // true when MF signed exactly this payload

$list = $anaf->messages($cui, days: 5, token: $token);         // FACTURA TRIMISA / PRIMITA, ERORI FACTURA, MESAJ CUMPARATOR …
$page = $anaf->messagesBetween($cui, $from, $to, page: 1, token: $token);
$pdf  = $anaf->render($xml);                                   // MF's PDF rendering, public
```

Errors are typed: `Unauthorised` (401/403 and "Nu aveti drept in SPV…"), `RateLimited` (429 and ANAF's daily-quota texts, with `retryAfter` and the `quota` hit), `UploadRefused` (ExecutionStatus 1, with ANAF's messages), `NotFound`, `TransportFailure` (5xx or the network — ANAF is down, the document is not wrong), `UnexpectedResponse` (with the body). `Quotas` holds MF's published limits for your rate limiter.

### OAuth

The certificate holder authorises once a year; the server keeps itself authorised with the refresh token, which needs no certificate.

```php
use AtlasFlow\EFacturaRo\Anaf\OAuth\{AuthorizationUrl, OAuthClient};

$url = AuthorizationUrl::build($clientId, $redirectUri);       // send the certificate holder here

$oauth = new OAuthClient($psr18Client, $psr17Factory, $psr17Factory, $clientId, $clientSecret);
$pair  = $oauth->exchange($code, $redirectUri);                // within ANAF's 60-second window
$pair  = $oauth->refresh($pair);                               // rotates both tokens: persist the whole pair
```

`TokenPair` reads `exp`, `iat` and the certificate `serial` from the JWTs without verifying them (ANAF verifies on use). No JWT claim naming the CUIs the holder has rights for has been confirmed, so `coveredCuis` is `[]` and an authorisation must be treated as covering exactly the CUI it was started for.

## Tests

```bash
composer test           # everything offline (~2 s)
composer test:live      # the eight fixtures against ANAF's public validator
ANAF_TEST_TOKEN=… ANAF_TEST_CIF=… vendor/bin/pest --group=anaf-test   # upload → poll → download, ANAF test environment
```

`tests/fixtures/anaf/` holds ANAF's documented responses; `tests/fixtures/mf/` holds the Ministry of Finance's sample invoice and credit note (EUPL 1.2). `resources/xsd/` is the OASIS UBL 2.1 schema set.

## Sources

- ANAF e-Factura API pages under `https://mfinante.gov.ro/static/10/eFactura/` — `upload.html`, `staremesaj.html`, `listamesaje.html`, `descarcare.html`, `validare.html`, `xmltopdf.html`, `validaresemnatura.html`, `limiteApeluriAPI.txt`, `ro16931-ubl-1.0.9.zip`, `exemple_Invoice_CreditNote.zip`
- ANAF OAuth: `Oauth_procedura_inregistrare_aplicatii_portal_ANAF.pdf`
- EN 16931-1 and the CEF validation artefacts as shipped in the MF schematron

## Licence

MIT. Copyright (c) 2026 AtlasFlow.
