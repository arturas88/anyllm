<?php

/**
 * Structured Outputs Example with OpenRouter
 *
 * Demonstrates using OpenRouter's structured outputs feature. The library automatically:
 * - Sends schema via response_format parameter
 * - Enhances prompts with field names from PHP classes
 * - Transforms and hydrates responses to match your schema
 *
 * Use models that support structured outputs (e.g., google/gemini-3-flash-preview)
 * See: https://openrouter.ai/docs/guides/features/structured-outputs
 */

require __DIR__ . '/../bootstrap.php';

use AnyLLM\AnyLLM;
use AnyLLM\Enums\Provider;
use AnyLLM\Messages\UserMessage;
use AnyLLM\StructuredOutput\Attributes\{Description, ArrayOf};

$prompts = [];

$prompts['classification'] = <<<'PROMPT'
You are a document classification system for European traffic-related documents.

Analyze the provided document and determine its type, stage, and key characteristics.

**Instructions:**
1. Identify the primary document type (fine, notice, reminder, etc.)
2. Determine the processing stage (original, reminder, collection, enforcement, etc.)
3. Extract country code and languages present
4. Identify the issuing authority type and name
5. Determine if driver identification is required
6. Assess if this is an actionable fine requiring payment
7. Provide confidence score (0.0-1.0)

Extract classification information accurately.
PROMPT;

$prompts['extraction'] = <<<'PROMPT'
You are a specialized document parser for traffic violation documents from various European countries.

Analyze the provided document and extract ALL available information comprehensively.

**CRITICAL INSTRUCTIONS:**
1. Extract data EXACTLY as it appears in the document
2. Use ISO standards: dates (YYYY-MM-DD), times (HH:MM:SS), dates and times (YYYY-MM-DD HH:MM:SS), country codes (ISO 3166), currency codes (ISO 4217)
3. If a field is not present or unclear, set it to null
4. For amounts, extract only numeric values
5. Preserve original language text in description fields
6. Be extremely careful with dates - verify day/month/year order based on document language
7. Extract all payment tiers: early payment, standard, late payment with respective amounts and deadlines
8. For speed violations, extract all speed-related data including tolerance
9. Include driver identification requirements if present
10. Extract appeal information and deadlines
11. Note access details for evidence/photo portals
PROMPT;

// ========================================
// CLASSIFICATION SCHEMA
// ========================================

class DocumentClassification
{
    #[Description('Primary document type: traffic_fine, parking_fine, toll_notice, driver_identification, collection_notice, payment_reminder, appeal_response, unknown')]
    public ?string $document_type = null;

    #[Description('Current stage in the process: original, reminder, collection, enforcement, court, resolved')]
    public ?string $document_stage = null;

    #[Description('ISO 3166-1 alpha-2 country code of issuing authority (AT, IT, NL, NO, DE, PL, LT, etc.)')]
    public ?string $country_code = null;

    #[ArrayOf('string')]
    #[Description('Languages present in the document (ISO 639-1 language codes)')]
    public array $document_languages = [];

    #[Description('Type of authority: police, municipality, private_company, toll_operator, collection_agency, law_firm')]
    public ?string $issuing_authority_type = null;

    #[Description('Name of the issuing authority')]
    public ?string $issuing_authority_name = null;

    #[Description('True if document requests driver identification')]
    public ?bool $requires_driver_identification = null;

    #[Description('True if this document requires payment (false for pure ID requests)')]
    public ?bool $is_actionable_fine = null;

    #[Description('Classification confidence 0.0-1.0')]
    public ?float $confidence_score = null;
}

// ========================================
// TRAFFIC DOCUMENT EXTRACTION SCHEMA
// ========================================

final class TrafficDocumentExtraction
{
    #[Description('Document identification and high-level metadata extracted from the notice.')]
    public ?Document $document = null;

    #[ArrayOf(Party::class)]
    #[Description('All organizations mentioned on the document. Include issuer + any processor/collector/law firm/court.')]
    public array $parties = [];

    #[Description('Vehicle identified in the notice.')]
    public ?Vehicle $vehicle = null;

    #[Description('Person or company receiving the notice.')]
    public ?Addressee $addressee = null;

    #[Description('Violation details (time, type, location, legal basis).')]
    public ?Violation $violation = null;

    #[Description('Speed-specific details if the violation is speed-related; otherwise null.')]
    public ?SpeedData $speed_data = null;

    #[Description('Amounts, fees, and payment tiers (early/standard/late/enforcement).')]
    public ?FinancialSummary $financial = null;

    #[ArrayOf(PaymentMethod::class)]
    #[Description('Payment methods available on the document. Can include bank transfer and/or online portal options.')]
    public array $payment_methods = [];

    #[Description('Driver identification request details (if the document asks to identify the driver).')]
    public ?DriverIdentification $driver_identification = null;

    #[Description('Appeal/objection information and deadlines if available.')]
    public ?Appeal $appeal = null;

    #[Description('How to access photographic or other evidence (portal URL + access codes).')]
    public ?EvidenceAccess $evidence_access = null;

    #[Description('Metadata about the extraction process and any uncertainties.')]
    public ?ExtractionMetadata $extraction_metadata = null;
}

final class Document
{
    #[Description('Document type enum: traffic_fine, parking_fine, toll_notice, driver_identification, collection_notice, payment_reminder, court_decision, other, unknown.')]
    public ?string $type = null;

    #[Description('Processing stage enum: original, reminder, collection, enforcement, unknown.')]
    public ?string $stage = null;

    #[Description('Document issue date formatted as YYYY-MM-DD (e.g. 2025-03-19).')]
    public ?string $issued_at = null;

    #[Description('Service/delivery/notification date if explicitly stated; format YYYY-MM-DD. If not stated, null.')]
    public ?string $served_at = null;

    #[ArrayOf('string')]
    #[Description('Detected languages present in the document as ISO 639-1 codes (e.g. ["it","en"]).')]
    public array $languages = [];

    #[Description('Primary language of the document if clearly identifiable; ISO 639-1 (e.g. "it").')]
    public ?string $primary_language = null;

    #[Description('Issuing country code if determinable from the document; ISO 3166-1 alpha-2 (e.g. "IT").')]
    public ?string $issuer_country_code = null;

    #[ArrayOf(DocumentIdentifier::class)]
    #[Description('All identifiers printed on the notice (case/protocol/notice/decision/etc.). Preserve raw formatting.')]
    public array $identifiers = [];
}

final class DocumentIdentifier
{
    #[Description('Identifier type enum: case_number, protocol_number, notice_number, decision_number, internal_registry_id, payment_reference, web_access_code, barcode_id, other, unknown.')]
    public string $type;

    #[Description('Identifier value exactly as printed (string). Keep slashes/dashes/spaces.')]
    public string $value;

    #[Description('Label text near the value, if available (e.g. "CJIB-nummer", "Protocol number", "ROIK").')]
    public ?string $label = null;

    #[Description('Where it appears enum: header, payment_section, portal_section, footer, envelope, attachment, unknown.')]
    public ?string $context = null;
}

final class Party
{
    #[Description('Party role enum: issuer, processor, police, municipality, regional_authority, toll_operator, private_parking, collection_agency, law_firm, court, other, unknown.')]
    public ?string $role = null;

    #[Description('Full organization name as printed.')]
    public ?string $name = null;

    #[Description('Country of the party as ISO 3166-1 alpha-2 (e.g. "NL").')]
    public ?string $country_code = null;

    #[Description('City/municipality name (free text as printed).')]
    public ?string $city = null;

    #[Description('Region/province/state name (free text as printed).')]
    public ?string $region = null;

    #[Description('Postal address structured into fields.')]
    public ?PostalAddress $address = null;

    #[Description('Contact details (phone/email/website).')]
    public ?ContactDetails $contact = null;
}

final class PostalAddress
{
    #[Description('Street address line 1 (e.g. "Via Roma 12").')]
    public ?string $line1 = null;

    #[Description('Street address line 2 / building / unit / PO box (optional).')]
    public ?string $line2 = null;

    #[Description('Postal/ZIP code as printed (string; keep leading zeros).')]
    public ?string $postal_code = null;

    #[Description('City/locality as printed.')]
    public ?string $city = null;

    #[Description('Region/province/state as printed.')]
    public ?string $region = null;

    #[Description('Country code as ISO 3166-1 alpha-2 (e.g. "DE").')]
    public ?string $country_code = null;
}

final class ContactDetails
{
    #[Description('Contact phone number as printed. Keep spaces/+ prefixes.')]
    public ?string $phone = null;

    #[Description('Contact email address in standard email format (e.g. "info@example.com").')]
    public ?string $email = null;

    #[Description('Website URL (absolute) if present (e.g. "https://example.org").')]
    public ?string $website = null;
}

final class Vehicle
{
    #[Description('License plate/registration number as printed.')]
    public ?string $registration_number = null;

    #[Description('Vehicle Identification Number (VIN), 17 characters if present.')]
    public ?string $vin = null;

    #[Description('Country of registration as ISO 3166-1 alpha-2 (e.g. "LT").')]
    public ?string $registration_country_code = null;

    #[Description('Vehicle type enum: car, motorcycle, van, bus, truck, lorry, tractor, trailer, semitrailer, autotrain, autotrailer, other, unknown.')]
    public ?string $type = null;

    #[Description('Manufacturer/brand (e.g. "Volkswagen", "Volvo", "Schmitz", "MAN").')]
    public ?string $make = null;

    #[Description('Model (e.g. "Passat", "XC90", "Q7", "FH500", "Actros").')]
    public ?string $model = null;
}

final class Addressee
{
    #[Description('Full name of person or company as printed.')]
    public ?string $name = null;

    #[Description('True if addressee is a company; false if person; null if unknown.')]
    public ?bool $is_company = null;

    #[Description('Street address line 1 (e.g. "Gedimino pr. 1").')]
    public ?string $street_address = null;

    #[Description('City/locality as printed.')]
    public ?string $city = null;

    #[Description('Postal/ZIP code as printed (string; keep leading zeros).')]
    public ?string $postal_code = null;

    #[Description('Country code as ISO 3166-1 alpha-2 (e.g. "LT").')]
    public ?string $country_code = null;

    #[Description('Role enum: owner, lessee, driver, operator, responsible_party, keeper, unknown.')]
    public ?string $role = null;
}

final class Violation
{
    #[Description('Violation datetime in ISO 8601: YYYY-MM-DDTHH:MM:SS (use seconds if available; otherwise omit seconds).')]
    public ?string $violation_at = null;

    #[Description('Timezone for violation_at as IANA TZ (e.g. "Europe/Rome", "Europe/Amsterdam"). Null if unknown.')]
    public ?string $timezone = null;

    #[Description('Validation/confirmation datetime if separately stated; ISO 8601 (YYYY-MM-DDTHH:MM[:SS]).')]
    public ?string $validated_at = null;

    #[Description('Violation type enum: speeding, excessive_speeding, red_light, parking_violation, toll_evasion, lane_violation, illegal_overtaking, no_insurance, no_registration, dangerous_driving, mobile_phone, seatbelt, wrong_category_parking, overtime_parking, no_payment_parking, other, unknown.')]
    public ?string $type = null;

    #[Description('Free-text description of the violation as printed, in the document language.')]
    public ?string $description = null;

    #[Description('Legal article/law reference as printed (e.g. "Art. 142 C.d.S.").')]
    public ?string $law_reference = null;

    #[Description('Structured location information (road, km marker, municipality, geo).')]
    public ?Location $location = null;
}

final class Location
{
    #[Description('Complete location text as printed (can include city, road, direction).')]
    public ?string $full_text = null;

    #[Description('Road/street name (free text).')]
    public ?string $road_name = null;

    #[Description('Road type enum: highway, national, regional, municipal, private, unknown.')]
    public ?string $road_type = null;

    #[Description('Kilometer marker/position as printed (string, may include "+").')]
    public ?string $kilometer_marker = null;

    #[Description('Travel direction as printed (e.g. "towards Milano").')]
    public ?string $direction = null;

    #[Description('Municipality/commune/city related to violation location.')]
    public ?string $municipality = null;

    #[Description('GPS point if present or reliably derived.')]
    public ?GeoPoint $geo = null;
}

final class GeoPoint
{
    #[Description('Latitude in decimal degrees (e.g. 54.6872).')]
    public ?float $lat = null;

    #[Description('Longitude in decimal degrees (e.g. 25.2797).')]
    public ?float $lon = null;

    #[Description('Source enum: stated, inferred, geocoded, unknown. Use stated only if printed in the document.')]
    public ?string $source = null;
}

final class SpeedData
{
    #[Description('Posted speed limit in km/h (number).')]
    public ?float $limit_kmh = null;

    #[Description('Measured speed in km/h before tolerance (number).')]
    public ?float $measured_kmh = null;

    #[Description('Speed after tolerance deduction in km/h (number).')]
    public ?float $corrected_kmh = null;

    #[Description('Amount over the limit in km/h (number).')]
    public ?float $excess_kmh = null;

    #[Description('Tolerance percentage applied (e.g. 5.0).')]
    public ?float $tolerance_percent = null;

    #[Description('Tolerance value in km/h applied (number).')]
    public ?float $tolerance_kmh = null;

    #[Description('Measurement device type/name as printed (e.g. "Autovelox").')]
    public ?string $measurement_device = null;

    #[Description('Device serial number or ID as printed.')]
    public ?string $device_serial = null;

    #[Description('Certification information as printed (free text).')]
    public ?string $device_certification = null;

    #[Description('Last calibration date if stated; format YYYY-MM-DD.')]
    public ?string $device_calibrated_at = null;
}

final class Money
{
    #[Description('Currency as ISO 4217 (e.g. "EUR", "NOK", "PLN").')]
    public string $currency;

    #[Description('Decimal amount as a string to avoid float rounding (e.g. "123.45").')]
    public string $amount;
}

final class Fee
{
    #[Description('Fee type enum: administrative, notification, collection, legal, interest, other, unknown.')]
    public ?string $type = null;

    #[Description('Fee amount as Money (currency + decimal string).')]
    public ?Money $amount = null;

    #[Description('Free-text fee label as printed (optional).')]
    public ?string $label = null;
}

final class PaymentTier
{
    #[Description('Tier kind enum: early, standard, late, enforcement, other, unknown.')]
    public ?string $kind = null;

    #[Description('Tier amount payable as Money (currency + decimal string).')]
    public ?Money $amount = null;

    #[Description('Original tier description/instructions as printed (optional).')]
    public ?string $description = null;

    #[Description('Deadline date for this tier; format YYYY-MM-DD if explicitly stated.')]
    public ?string $deadline = null;

    #[Description('Number of days allowed if expressed as days (e.g. "within 5 days"); integer.')]
    public ?int $days = null;

    #[Description('Basis enum for days calculation: issue_date, service_date, violation_date, unknown.')]
    public ?string $basis = null;
}

final class FinancialSummary
{
    #[Description('Base fine amount before additional fees; Money.')]
    public ?Money $base_fine = null;

    #[ArrayOf(Fee::class)]
    #[Description('Additional fees/costs listed on the notice (admin/notification/collection/legal/etc.).')]
    public array $fees = [];

    #[ArrayOf(PaymentTier::class)]
    #[Description('Payment tiers/steps (early/standard/late/enforcement) with deadlines and/or day rules.')]
    public array $tiers = [];

    #[Description('Maximum enforceable amount if explicitly stated; Money.')]
    public ?Money $maximum_amount = null;
}

final class PaymentMethod
{
    #[Description('Payment method type enum: bank_transfer, online_portal, card, cash, other, unknown.')]
    public ?string $type = null;

    #[Description('Free-text instructions for this payment method as printed (optional).')]
    public ?string $description = null;

    #[Description('Bank name for transfer if stated.')]
    public ?string $bank_name = null;

    #[Description('IBAN for transfer if stated. Store WITHOUT spaces, uppercase (e.g. "IT32T0200809292V00420262239").')]
    public ?string $iban = null;

    #[Description('BIC/SWIFT code if stated (8 or 11 characters).')]
    public ?string $bic_swift = null;

    #[Description('Account holder name if stated.')]
    public ?string $account_holder = null;

    #[Description('Payment reference to include with payment (often mandatory). Keep raw formatting.')]
    public ?string $payment_reference = null;

    #[Description('Online payment portal URL (absolute) if available.')]
    public ?string $online_url = null;

    #[Description('Portal username/ID if explicitly provided (avoid guessing).')]
    public ?string $portal_username = null;

    #[Description('Portal access code/PIN if printed. Do NOT treat as a real password; keep raw formatting.')]
    public ?string $portal_access_code = null;

    #[Description('Additional payment code/ID (e.g. creditor ID, variable symbol, structured payment code).')]
    public ?string $payment_code = null;

    #[Description('Creditor/tax identifier if stated (e.g. creditor ID / VAT / fiscal code).')]
    public ?string $creditor_id = null;
}

final class DriverIdentification
{
    #[Description('True if the document requests driver identification; null if not mentioned.')]
    public ?bool $is_required = null;

    #[Description('Number of days allowed to respond (integer) if stated as days.')]
    public ?int $response_days = null;

    #[Description('Specific response deadline date if stated; format YYYY-MM-DD.')]
    public ?string $response_deadline = null;

    #[Description('Online portal URL for driver identification response (absolute URL) if available.')]
    public ?string $response_portal_url = null;

    #[Description('Postal address for sending response (free text) if provided; keep as printed.')]
    public ?string $response_address_text = null;

    #[Description('Page number (1-based) containing the response form if referenced; integer.')]
    public ?int $form_page = null;

    #[ArrayOf('string')]
    #[Description('List of required information items requested (e.g. ["driver_name","driver_address","license_number"]). Use stable keys.')]
    public ?array $required_information = null;

    #[Description('Penalty amount for not responding if stated. Decimal string preferred; float allowed if unavoidable.')]
    public ?float $penalty_for_non_response = null;

    #[Description('Currency for penalty_for_non_response if used (ISO 4217).')]
    public ?string $penalty_currency = null;
}

final class Appeal
{
    #[Description('Number of days allowed to appeal (integer) if stated.')]
    public ?int $appeal_days = null;

    #[Description('Appeal deadline date if stated; format YYYY-MM-DD.')]
    public ?string $appeal_deadline = null;

    #[Description('Appeal authority name as printed (e.g. "Prefetto di ...", "Court of ...").')]
    public ?string $appeal_authority = null;

    #[Description('Appeal authority type enum: prefect, justice_of_peace, court, administrative_body, other, unknown.')]
    public ?string $appeal_authority_type = null;

    #[Description('Address for appeal submission (free text) if present.')]
    public ?string $appeal_address_text = null;

    #[ArrayOf('string')]
    #[Description('Accepted languages for appeal as ISO 639-1 codes if stated (e.g. ["it"]).')]
    public ?array $appeal_languages = null;

    #[Description('True if a fee/payment is required to file an appeal; null if not mentioned.')]
    public ?bool $payment_required_for_appeal = null;

    #[Description('Appeal fee amount if stated; float allowed.')]
    public ?float $appeal_fee_amount = null;

    #[Description('Appeal fee currency (ISO 4217) if appeal_fee_amount is used.')]
    public ?string $appeal_fee_currency = null;
}

final class EvidenceAccess
{
    #[Description('Portal URL to view evidence (photos/video) if available; absolute URL.')]
    public ?string $portal_url = null;

    #[Description('Access ID/username printed for evidence portal, if present.')]
    public ?string $access_id = null;

    #[Description('Access code/PIN for evidence portal as printed.')]
    public ?string $access_code = null;

    #[Description('Additional verification code if used (e.g. ADI code); keep as printed.')]
    public ?string $verification_code = null;
}

final class ExtractionMetadata
{
    #[Description('Overall extraction confidence score from 0.0 to 1.0.')]
    public ?float $confidence_score = null;

    #[Description('Overall extraction quality enum: high, medium, low.')]
    public ?string $confidence_level = null;

    #[ArrayOf('string')]
    #[Description('List of field paths that are uncertain (e.g. ["document.identifiers[0].value","financial.tiers[1].deadline"]).')]
    public ?array $uncertain_fields = null;

    #[ArrayOf('string')]
    #[Description('Assumptions made during extraction (free text).')]
    public ?array $assumptions_made = null;

    #[ArrayOf('string')]
    #[Description('Expected fields not found (field paths) (e.g. ["vehicle.registration_number"]).')]
    public ?array $missing_fields = null;

    #[Description('Notes about extraction quality, OCR artifacts, or ambiguities (free text).')]
    public ?string $extraction_notes = null;

    #[Description('True if OCR was required to read the document; false if text was selectable; null if unknown.')]
    public ?bool $ocr_required = null;

    #[Description('Primary language actually used for extraction (ISO 639-1), which may differ from document.primary_language.')]
    public ?string $primary_language_extracted = null;

    #[Description('Extractor/schema version string (e.g. "traffic-extract-v2.1").')]
    public ?string $schema_version = null;

    #[Description('Optional model identifier used for extraction (e.g. "gpt-5.2").')]
    public ?string $model_id = null;
}

// ========================================
// HELPER FUNCTIONS
// ========================================

/**
 * Safely get amount and currency from a Money object.
 * Returns null if Money is null or properties are uninitialized.
 * 
 * @return array{amount: string, currency: string}|null
 */
function getMoneyValue(?Money $money): ?array
{
    if (!$money) {
        return null;
    }
    
    try {
        if (isset($money->amount) && isset($money->currency)) {
            return [
                'amount' => $money->amount,
                'currency' => $money->currency,
            ];
        }
    } catch (\Error $e) {
        // Property not initialized
    }
    
    return null;
}

// ========================================
// USAGE EXAMPLE
// ========================================

$pdfFilePath = __DIR__ . '/document-road-fine-1.pdf';

// Validate file exists
if (!file_exists($pdfFilePath)) {
    die("PDF file not found: {$pdfFilePath}\n");
}

$useModel = 'google/gemini-3-flash-preview';

/** @var \AnyLLM\Providers\AbstractProvider $llm */
$llm = AnyLLM::provider(Provider::OpenRouter)
    ->apiKey($_ENV['OPENROUTER_API_KEY'] ?? 'your-key')
    ->model($useModel)
    ->build()
    ->withRetry(maxRetries: 3, initialDelayMs: 1000)
    ->withDebugging(); // Logs all HTTP requests/responses to stdout

echo "=== CLASSIFICATION ===\n";

$classificationMessage = UserMessage::withFiles($prompts['classification'], [$pdfFilePath]);

try {
    $classificationResponse = $llm->withRetry(maxRetries: 5)->generateObject(
        model: $useModel,
        prompt: [$classificationMessage],
        schema: DocumentClassification::class,
    );

    $classification = $classificationResponse->object;

    echo "Classification Result:\n";
    echo "  Document Type: " . ($classification->document_type ?? 'N/A') . "\n";
    echo "  Stage: " . ($classification->document_stage ?? 'N/A') . "\n";
    echo "  Country: " . ($classification->country_code ?? 'N/A') . "\n";
    echo "  Confidence: " . ($classification->confidence_score ?? 'N/A') . "\n";
} catch (\Exception $e) {
    echo "Classification Error: " . $e->getMessage() . "\n";
    echo "This might indicate:\n";
    echo "  1. The model doesn't support structured outputs\n";
    echo "  2. The schema needs adjustment\n";
    echo "  3. Network/API issue - try again\n";
}

echo "\n=== FULL EXTRACTION ===\n";

$extractionMessage = UserMessage::withFiles($prompts['extraction'], [$pdfFilePath]);

try {
    $extractionResponse = $llm->withRetry(maxRetries: 5)->generateObject(
        model: $useModel,
        prompt: [$extractionMessage],
        schema: TrafficDocumentExtraction::class,
    );

    $extraction = $extractionResponse->object;

    echo "Extraction completed successfully!\n";
    echo "The response object is automatically transformed and properly typed.\n";
} catch (\Exception $e) {
    echo "Extraction Error: " . $e->getMessage() . "\n";
    echo "This might indicate:\n";
    echo "  1. The model doesn't support structured outputs\n";
    echo "  2. The schema is too complex - try simplifying it\n";
    echo "  3. Network/API issue - try again\n";
    echo "  4. Model rejected the request - check your prompt\n";
    exit(1);
}

// Debug output - uncomment to see raw response and hydrated object
// echo "\n=== RAW RESPONSE DATA ===\n";
// echo json_encode($extractionResponse->raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
// echo "\n\n=== HYDRATED OBJECT ===\n";
// print_r($extractionResponse->object);
// echo "\n";
// die();

// Display summary
echo "\n=== DOCUMENT SUMMARY ===\n";

// Check if extraction has the expected structure
if (isset($extraction->document)) {
    echo "Document Type: {$extraction->document->type}\n";
    echo "Document Stage: {$extraction->document->stage}\n";
    if (!empty($extraction->document->identifiers)) {
        $refs = array_map(fn($id) => $id->value, $extraction->document->identifiers);
        echo "Reference: " . implode(', ', $refs) . "\n";
    }
    if ($extraction->document->issued_at) {
        echo "Date: {$extraction->document->issued_at}\n";
    }
} else {
    echo "Note: Document data not available in expected format.\n";
}

// Find issuer from parties array
$issuer = null;
if (!empty($extraction->parties)) {
    foreach ($extraction->parties as $party) {
        if ($party->role === 'issuer') {
            $issuer = $party;
            break;
        }
    }
    if (!$issuer && !empty($extraction->parties)) {
        $issuer = $extraction->parties[0]; // Fallback to first party
    }
}

if ($issuer) {
    echo "\nAuthority: {$issuer->name}";
    if ($issuer->country_code) {
        echo " ({$issuer->country_code})";
    }
    echo "\n";
    if ($issuer->role) {
        echo "Authority Type: {$issuer->role}\n";
    }
}

if (isset($extraction->vehicle)) {
    echo "\nVehicle: {$extraction->vehicle->registration_number}\n";
    if ($extraction->vehicle->make) {
        echo "Make/Model: {$extraction->vehicle->make} {$extraction->vehicle->model}\n";
    }
}

if (isset($extraction->violation)) {
    echo "\nViolation Type: {$extraction->violation->type}\n";
    if ($extraction->violation->violation_at) {
        echo "Violation Date/Time: {$extraction->violation->violation_at}\n";
    }
    if ($extraction->violation->location && $extraction->violation->location->full_text) {
        echo "Location: {$extraction->violation->location->full_text}\n";
    }
}

if (isset($extraction->speed_data) && $extraction->speed_data && $extraction->speed_data->limit_kmh) {
    echo "\nSpeed Data:\n";
    echo "  Limit: {$extraction->speed_data->limit_kmh} km/h\n";
    echo "  Measured: {$extraction->speed_data->measured_kmh} km/h\n";
    if ($extraction->speed_data->corrected_kmh) {
        echo "  Corrected: {$extraction->speed_data->corrected_kmh} km/h\n";
    }
    echo "  Excess: {$extraction->speed_data->excess_kmh} km/h\n";
}

if (isset($extraction->financial)) {
    echo "\nFinancial:\n";
    
    // Get currency from first tier or base_fine
    $currency = null;
    $baseFineValue = getMoneyValue($extraction->financial->base_fine ?? null);
    if ($baseFineValue) {
        $currency = $baseFineValue['currency'];
    } elseif (!empty($extraction->financial->tiers) && $extraction->financial->tiers[0]->amount) {
        $tierAmountValue = getMoneyValue($extraction->financial->tiers[0]->amount);
        if ($tierAmountValue) {
            $currency = $tierAmountValue['currency'];
        }
    }
    
    if ($currency) {
        echo "  Currency: {$currency}\n";
    }
    
    if ($baseFineValue) {
        echo "  Base Fine: {$baseFineValue['amount']} {$baseFineValue['currency']}\n";
    }
    
    if (!empty($extraction->financial->tiers)) {
        foreach ($extraction->financial->tiers as $tier) {
            $tierLabel = $tier->kind ? ucfirst($tier->kind) : 'Payment';
            $tierAmountValue = getMoneyValue($tier->amount);
            if ($tierAmountValue) {
                echo "  {$tierLabel}: {$tierAmountValue['amount']} {$tierAmountValue['currency']}";
                if ($tier->deadline) {
                    echo " (by {$tier->deadline})";
                } elseif ($tier->days) {
                    echo " (within {$tier->days} days)";
                }
                if ($tier->description) {
                    echo " - {$tier->description}";
                }
                echo "\n";
            }
        }
    }
    
    $maxAmountValue = getMoneyValue($extraction->financial->maximum_amount ?? null);
    if ($maxAmountValue) {
        echo "  Maximum Amount: {$maxAmountValue['amount']} {$maxAmountValue['currency']}\n";
    }
}

if (!empty($extraction->payment_methods)) {
    echo "\nPayment Methods:\n";
    foreach ($extraction->payment_methods as $method) {
        if ($method->type === 'bank_transfer') {
            if ($method->iban) {
                echo "  IBAN: {$method->iban}\n";
            }
            if ($method->bic_swift) {
                echo "  BIC/SWIFT: {$method->bic_swift}\n";
            }
            if ($method->bank_name) {
                echo "  Bank: {$method->bank_name}\n";
            }
            if ($method->account_holder) {
                echo "  Account Holder: {$method->account_holder}\n";
            }
            if ($method->payment_reference) {
                echo "  Reference: {$method->payment_reference}\n";
            }
        } elseif ($method->type === 'online_portal') {
            if ($method->online_url) {
                echo "  Online Portal: {$method->online_url}\n";
            }
            if ($method->portal_username) {
                echo "  Portal Username: {$method->portal_username}\n";
            }
        }
    }
}

if (isset($extraction->driver_identification) && $extraction->driver_identification && $extraction->driver_identification->is_required) {
    echo "\nDriver Identification Required:\n";
    if ($extraction->driver_identification->response_deadline) {
        echo "  Deadline: {$extraction->driver_identification->response_deadline}\n";
    } elseif ($extraction->driver_identification->response_days) {
        echo "  Response Days: {$extraction->driver_identification->response_days}\n";
    }
    if ($extraction->driver_identification->response_portal_url) {
        echo "  Portal: {$extraction->driver_identification->response_portal_url}\n";
    }
    if ($extraction->driver_identification->response_address_text) {
        echo "  Address: {$extraction->driver_identification->response_address_text}\n";
    }
}

if (isset($extraction->appeal) && $extraction->appeal && $extraction->appeal->appeal_deadline) {
    echo "\nAppeal Information:\n";
    echo "  Deadline: {$extraction->appeal->appeal_deadline}\n";
    if ($extraction->appeal->appeal_authority) {
        echo "  Authority: {$extraction->appeal->appeal_authority}\n";
    }
}

if (isset($extraction->extraction_metadata)) {
    echo "\nExtraction Quality:\n";
    echo "  Confidence: {$extraction->extraction_metadata->confidence_level} ({$extraction->extraction_metadata->confidence_score})\n";
    echo "  Primary Language: {$extraction->extraction_metadata->primary_language_extracted}\n";
    echo "  OCR Required: " . ($extraction->extraction_metadata->ocr_required ? 'Yes' : 'No') . "\n";
}
