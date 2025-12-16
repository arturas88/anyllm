<?php

/**
 * Structured Outputs Example with OpenRouter
 * 
 * This example demonstrates how to correctly use OpenRouter's native structured outputs
 * feature with the AnyLLM library.
 * 
 * KEY POINTS:
 * 1. The schema is automatically sent via response_format parameter - you don't need to
 *    include it in your prompt text
 * 2. Field names are automatically extracted from your PHP class and appended to the prompt
 *    to ensure the model uses exact field names
 * 3. The model will automatically return JSON matching your schema exactly (with strict mode)
 * 4. Field name mismatches and nested structures are automatically transformed during hydration
 * 5. No manual transformation is needed - the library handles everything automatically
 * 6. Use models that support structured outputs (e.g., google/gemini-2.5-flash)
 * 
 * For more details, see: STRUCTURED_OUTPUTS_GUIDE.md
 * OpenRouter docs: https://openrouter.ai/docs/guides/features/structured-outputs
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
2. Use ISO standards: dates (YYYY-MM-DD), country codes (ISO 3166), currency codes (ISO 4217)
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

class Document
{
    #[Description('Main document/case reference number')]
    public ?string $primary_reference = null;

    #[Description('Secondary reference (protocol, verbale, etc.)')]
    public ?string $secondary_reference = null;

    #[Description('Date document was issued (YYYY-MM-DD)')]
    public ?string $document_date = null;

    #[ArrayOf('string')]
    #[Description('Languages in the document (ISO 639-1 codes)')]
    public array $languages = [];

    #[Description('Document type: traffic_fine, parking_fine, toll_notice, driver_identification, collection_notice, payment_reminder')]
    public ?string $document_type = null;

    #[Description('Processing stage: original, reminder, collection, enforcement')]
    public ?string $stage = null;
}

class Authority
{
    #[Description('Full name of authority')]
    public ?string $name = null;

    #[Description('ISO 3166-1 alpha-2 (AT, IT, NL, NO, DE, PL, LT, etc.)')]
    public ?string $country_code = null;

    #[Description('Authority type: police, municipality, regional_authority, toll_operator, private_parking, collection_agency, law_firm')]
    public ?string $type = null;

    #[Description('City/municipality')]
    public ?string $city = null;

    #[Description('Region/province/state')]
    public ?string $region = null;

    #[Description('Full postal address')]
    public ?string $address = null;

    #[Description('Contact phone')]
    public ?string $phone = null;

    #[Description('Contact email')]
    public ?string $email = null;

    #[Description('Website URL')]
    public ?string $website = null;
}

class Vehicle
{
    #[Description('License plate number')]
    public ?string $registration_number = null;

    #[Description('Country of registration (ISO 3166-1 alpha-2)')]
    public ?string $registration_country = null;

    #[Description('Vehicle category: car, truck, trailer, semi_trailer, bus, motorcycle, van, other, unknown')]
    public ?string $vehicle_category = null;

    #[Description('Manufacturer/brand')]
    public ?string $make = null;

    #[Description('Vehicle model')]
    public ?string $model = null;

    #[Description('Vehicle Identification Number')]
    public ?string $vin = null;
}

class Addressee
{
    #[Description('Full name of person or company')]
    public ?string $name = null;

    #[Description('True if addressee is a company')]
    public ?bool $is_company = null;

    #[Description('Street and number')]
    public ?string $street_address = null;

    #[Description('City')]
    public ?string $city = null;

    #[Description('Postal/ZIP code')]
    public ?string $postal_code = null;

    #[Description('Country')]
    public ?string $country = null;

    #[Description('Role of addressee: owner, lessee, driver, operator, responsible_party, unknown')]
    public ?string $role = null;
}

class Violation
{
    #[Description('Date of violation (YYYY-MM-DD)')]
    public ?string $date = null;

    #[Description('Time of violation (HH:MM or HH:MM:SS)')]
    public ?string $time = null;

    #[Description('Validation/confirmation datetime (YYYY-MM-DD HH:MM:SS or YYYY-MM-DD HH:MM) if different')]
    public ?string $datetime_validation = null;

    #[Description('Violation type: speeding, excessive_speeding, red_light, parking_violation, toll_evasion, lane_violation, illegal_overtaking, no_insurance, no_registration, dangerous_driving, mobile_phone, seatbelt, wrong_category_parking, overtime_parking, no_payment_parking, other, unknown')]
    public ?string $type = null;

    #[Description('Full description of violation')]
    public ?string $description = null;

    #[Description('Legal article/law reference')]
    public ?string $law_reference = null;

    #[Description('Complete location description')]
    public ?string $location_full = null;

    #[Description('Road/street name')]
    public ?string $road_name = null;

    #[Description('Road type: highway, national, regional, municipal, private')]
    public ?string $road_type = null;

    #[Description('Kilometer position')]
    public ?string $kilometer_marker = null;

    #[Description('Travel direction')]
    public ?string $direction = null;

    #[Description('Municipality/commune')]
    public ?string $municipality = null;

    #[Description('GPS coordinates')]
    public ?string $coordinates = null;
}

class SpeedData
{
    #[Description('Posted speed limit in km/h')]
    public ?float $limit_kmh = null;

    #[Description('Raw measured speed in km/h')]
    public ?float $measured_kmh = null;

    #[Description('Speed after tolerance deduction')]
    public ?float $corrected_kmh = null;

    #[Description('Amount over the limit')]
    public ?float $excess_kmh = null;

    #[Description('Tolerance percentage applied')]
    public ?float $tolerance_percent = null;

    #[Description('Tolerance in km/h applied')]
    public ?float $tolerance_kmh = null;

    #[Description('Speed camera/device type')]
    public ?string $measurement_device = null;

    #[Description('Device serial number')]
    public ?string $device_serial = null;

    #[Description('Certification info')]
    public ?string $device_certification = null;

    #[Description('Last calibration date')]
    public ?string $device_calibration_date = null;
}

class Financial
{
    #[Description('Primary currency (ISO 4217: EUR, NOK, PLN, CHF, etc.)')]
    public ?string $currency = null;

    #[Description('Discounted early payment amount')]
    public ?float $early_amount = null;

    #[Description('Early payment deadline (YYYY-MM-DD)')]
    public ?string $early_deadline = null;

    #[Description('Days for early discount')]
    public ?int $early_days = null;

    #[Description('Standard payment amount')]
    public ?float $standard_amount = null;

    #[Description('Standard payment deadline (YYYY-MM-DD)')]
    public ?string $standard_deadline = null;

    #[Description('Day range start')]
    public ?int $standard_days_from = null;

    #[Description('Day range end')]
    public ?int $standard_days_to = null;

    #[Description('Late/enforcement payment amount')]
    public ?float $late_amount = null;

    #[Description('Date after which late amount applies')]
    public ?string $late_after_date = null;

    #[Description('Amount after first increase')]
    public ?float $first_increase_amount = null;

    #[Description('Amount after second increase')]
    public ?float $second_increase_amount = null;

    #[Description('Maximum enforceable amount')]
    public ?float $maximum_amount = null;

    #[Description('Base fine before fees')]
    public ?float $base_fine = null;

    #[Description('Administrative costs')]
    public ?float $administrative_costs = null;

    #[Description('Notification costs')]
    public ?float $notification_costs = null;

    #[Description('Collection costs')]
    public ?float $collection_costs = null;

    #[Description('Legal/court costs')]
    public ?float $legal_costs = null;

    #[Description('Alternative currency')]
    public ?string $alt_currency = null;

    #[Description('Amount in alternative currency')]
    public ?float $alt_amount = null;
}

class Payment
{
    #[Description('Bank name')]
    public ?string $bank_name = null;

    #[Description('IBAN number')]
    public ?string $iban = null;

    #[Description('BIC/SWIFT code')]
    public ?string $bic_swift = null;

    #[Description('Account holder name')]
    public ?string $account_holder = null;

    #[Description('Reference to include with payment')]
    public ?string $payment_reference = null;

    #[Description('Online payment portal URL')]
    public ?string $online_url = null;

    #[Description('Portal username/ID')]
    public ?string $online_username = null;

    #[Description('Portal password')]
    public ?string $online_password = null;

    #[Description('Additional payment code/ID')]
    public ?string $payment_code = null;

    #[Description('Creditor/tax code')]
    public ?string $creditor_id = null;
}

class DriverIdentification
{
    #[Description('True if driver identification is requested')]
    public ?bool $is_required = null;

    #[Description('Days to respond')]
    public ?int $response_days = null;

    #[Description('Specific deadline date (YYYY-MM-DD)')]
    public ?string $response_deadline = null;

    #[Description('Online response portal URL')]
    public ?string $response_portal = null;

    #[Description('Postal address for response')]
    public ?string $response_address = null;

    #[Description('Page number containing response form')]
    public ?string $form_page = null;

    #[ArrayOf('string')]
    #[Description('Information required to provide')]
    public ?array $required_information = null;

    #[Description('Penalty for not responding (EUR)')]
    public ?float $penalty_for_non_response = null;
}

class Appeal
{
    #[Description('Days allowed to appeal')]
    public ?int $appeal_days = null;

    #[Description('Appeal deadline (YYYY-MM-DD)')]
    public ?string $appeal_deadline = null;

    #[Description('Authority to submit appeal to')]
    public ?string $appeal_authority = null;

    #[Description('Prefect, Justice of Peace, Court, etc.')]
    public ?string $appeal_authority_type = null;

    #[Description('Address for appeal submission')]
    public ?string $appeal_address = null;

    #[ArrayOf('string')]
    #[Description('Accepted languages for appeal')]
    public ?array $appeal_languages = null;

    #[Description('True if fee required to appeal')]
    public ?bool $payment_required_for_appeal = null;
}

class EvidenceAccess
{
    #[Description('URL to view evidence')]
    public ?string $portal_url = null;

    #[Description('Access ID/username')]
    public ?string $access_id = null;

    #[Description('Access password')]
    public ?string $access_password = null;

    #[Description('ADI code for verification')]
    public ?string $adi_code = null;
}

class ExtractionMetadata
{
    #[Description('Overall extraction confidence 0.0-1.0')]
    public ?float $confidence_score = null;

    #[Description('Overall extraction quality assessment: high, medium, low')]
    public ?string $confidence_level = null;

    #[ArrayOf('string')]
    #[Description('Fields with low extraction confidence or ambiguous data')]
    public ?array $uncertain_fields = null;

    #[ArrayOf('string')]
    #[Description('Any assumptions made during extraction')]
    public ?array $assumptions_made = null;

    #[ArrayOf('string')]
    #[Description('Expected fields not found in document')]
    public ?array $missing_fields = null;

    #[Description('Notes about extraction quality')]
    public ?string $extraction_notes = null;

    #[Description('True if OCR was needed')]
    public ?bool $ocr_required = null;

    #[Description('Main language used for extraction')]
    public ?string $primary_language_extracted = null;
}

class TrafficDocumentExtraction
{
    #[Description('Document identification and metadata')]
    public ?Document $document = null;

    #[Description('Issuing authority details')]
    public ?Authority $authority = null;

    #[Description('Vehicle identification')]
    public ?Vehicle $vehicle = null;

    #[Description('Person or company receiving the document')]
    public ?Addressee $addressee = null;

    #[Description('Details of the traffic violation')]
    public ?Violation $violation = null;

    #[Description('Speed violation specific data')]
    public ?SpeedData $speed_data = null;

    #[Description('All payment amounts and tiers')]
    public ?Financial $financial = null;

    #[Description('Payment method details')]
    public ?Payment $payment = null;

    #[Description('Driver identification request details')]
    public ?DriverIdentification $driver_identification = null;

    #[Description('Appeal/objection options')]
    public ?Appeal $appeal = null;

    #[Description('How to access photographic evidence')]
    public ?EvidenceAccess $evidence_access = null;

    #[Description('Metadata about the extraction process')]
    public ?ExtractionMetadata $extraction_metadata = null;
}

// ========================================
// USAGE EXAMPLE
// ========================================
// NOTE: Field name transformation and hydration are now handled automatically
// by the library when using generateObject() with a PHP class schema.
// ========================================

$pdfFilePath = __DIR__ . '/document-road-fine.pdf';

// Validate file exists
if (!file_exists($pdfFilePath)) {
    die("PDF file not found: {$pdfFilePath}\n");
}

// Create provider with retry enabled for HTTP-level errors
// NOTE: Use a model that supports structured outputs:
// - google/gemini-2.5-flash (recommended - fast and cost-effective)
// - google/gemini-pro-1.5 (more capable)
// - openai/gpt-4o (OpenAI models)
// - anthropic/claude-3.5-sonnet (Anthropic models)
// Check https://openrouter.ai/models?supported_parameters=structured_outputs for full list
$useModel = 'google/gemini-2.5-flash';

$llm = AnyLLM::provider(Provider::OpenRouter)
    ->apiKey($_ENV['OPENROUTER_API_KEY'] ?? 'your-key')
    ->model($useModel)  // This model supports structured outputs
    ->build()
    ->withRetry(maxRetries: 3, initialDelayMs: 1000); // Retry HTTP errors

// Option 1: Classification only
// NOTE: Schema is automatically sent via response_format - no need to include in prompt
echo "=== CLASSIFICATION ===\n";

$classificationMessage = UserMessage::withFiles($prompts['classification'], [$pdfFilePath]);

try {
    $classificationResponse = $llm->withRetry(maxRetries: 5)->generateObject(
        model: $useModel,
        prompt: [$classificationMessage],
        schema: DocumentClassification::class,
    );
    
    // The response->object is already a properly typed DocumentClassification instance
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

// NOTE: 
// 1. Schema is automatically sent via response_format parameter
// 2. Prompt is automatically enhanced with exact field names from the PHP class
// 3. Response is automatically transformed and hydrated to match the schema
$extractionMessage = UserMessage::withFiles($prompts['extraction'], [$pdfFilePath]);

try {
    // Option 2: Full extraction
    // The library automatically:
    // 1. Enhances the prompt with exact field names from the schema
    // 2. Sends the schema via response_format to the model
    // 3. Transforms and hydrates the response to match the PHP class structure
    $extractionResponse = $llm->withRetry(maxRetries: 5)->generateObject(
        model: $useModel,
        prompt: [$extractionMessage],
        schema: TrafficDocumentExtraction::class,
    );
    
    // The response->object is already a properly typed TrafficDocumentExtraction instance
    // Field name mismatches and nested structures are automatically handled
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

// Debug output (uncomment to see raw response and hydrated object)
// echo "Extraction:\n";
// print_r($extractionResponse->raw);
// print_r($extraction);
// echo "\n";

// Display summary
echo "\n=== DOCUMENT SUMMARY ===\n";

// Check if extraction has the expected structure
if (isset($extraction->document)) {
    echo "Document Type: {$extraction->document->document_type}\n";
    echo "Document Stage: {$extraction->document->stage}\n";
    echo "Reference: {$extraction->document->primary_reference}\n";
    echo "Date: {$extraction->document->document_date}\n";
} else {
    echo "Note: Document data not available in expected format.\n";
    // Uncomment to debug:
    // echo "Raw extraction data structure:\n";
    // print_r($extraction);
}

if (isset($extraction->authority)) {
    echo "\nAuthority: {$extraction->authority->name} ({$extraction->authority->country_code})\n";
    echo "Authority Type: {$extraction->authority->type}\n";
}

if (isset($extraction->vehicle)) {
    echo "\nVehicle: {$extraction->vehicle->registration_number}\n";
    if ($extraction->vehicle->make) {
        echo "Make/Model: {$extraction->vehicle->make} {$extraction->vehicle->model}\n";
    }
}

if (isset($extraction->violation)) {
    echo "\nViolation Type: {$extraction->violation->type}\n";
    echo "Violation Date: {$extraction->violation->date}";
    if ($extraction->violation->time) {
        echo " at {$extraction->violation->time}";
    }
    echo "\nLocation: {$extraction->violation->location_full}\n";
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
    echo "  Currency: {$extraction->financial->currency}\n";
    if ($extraction->financial->early_amount) {
        echo "  Early Payment: {$extraction->financial->early_amount} {$extraction->financial->currency}";
        if ($extraction->financial->early_deadline) {
            echo " (by {$extraction->financial->early_deadline})";
        }
        echo "\n";
    }
    echo "  Standard Amount: {$extraction->financial->standard_amount} {$extraction->financial->currency}";
    if ($extraction->financial->standard_deadline) {
        echo " (by {$extraction->financial->standard_deadline})";
    }
    echo "\n";
    if ($extraction->financial->late_amount) {
        echo "  Late Amount: {$extraction->financial->late_amount} {$extraction->financial->currency}\n";
    }
}

if (isset($extraction->payment)) {
    echo "\nPayment Details:\n";
    echo "  IBAN: {$extraction->payment->iban}\n";
    echo "  Reference: {$extraction->payment->payment_reference}\n";
    if ($extraction->payment->online_url) {
        echo "  Online Portal: {$extraction->payment->online_url}\n";
    }
}

if (isset($extraction->driver_identification) && $extraction->driver_identification && $extraction->driver_identification->is_required) {
    echo "\nDriver Identification Required:\n";
    echo "  Deadline: {$extraction->driver_identification->response_deadline}\n";
    if ($extraction->driver_identification->response_portal) {
        echo "  Portal: {$extraction->driver_identification->response_portal}\n";
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