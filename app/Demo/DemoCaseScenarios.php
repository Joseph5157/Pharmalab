<?php

namespace App\Demo;

/**
 * The eight fictional, de-identified demo cases. Content only — no names,
 * hospital numbers or dates of birth. Each scenario's diagnoses, vitals,
 * investigations, medicines, SOAP note and activities agree with each other.
 *
 * @phpstan-type DemoComment array{section: string, body: string, is_flagged: bool}
 * @phpstan-type DemoStep array{action: 'submit'|'approve'|'return'|'reopen'|'correct', summary?: string|null, reason?: string, comments?: list<DemoComment>, medications?: array<string, array<string, mixed>>, soap?: array<string, mixed>}
 * @phpstan-type DemoContent array{case: array<string, mixed>, profile: array<string, mixed>, vitals: list<array<string, mixed>>, investigations: list<array<string, mixed>>, medications: list<array<string, mixed>>, adr: array<string, mixed>|null, counselling: array<string, mixed>|null, interventions: list<array<string, mixed>>, soap: array<string, mixed>|null}
 * @phpstan-type DemoScenario array{key: string, student: string, case_number: int, category: string|null, content: DemoContent, steps: list<DemoStep>}
 */
final class DemoCaseScenarios
{
    /** @return list<DemoScenario> */
    public static function all(): array
    {
        return [self::p1(), self::p2(), self::p3(), self::p4(), self::p5(), self::p6(), self::r1(), self::r2()];
    }

    /** @return DemoScenario */
    private static function p1(): array
    {
        return [
            'key' => 'P1', 'student' => DemoDataset::STUDENT1, 'case_number' => 1, 'category' => 'Cardiology',
            'content' => [
                'case' => ['encounter_date' => '2026-09-08', 'age_value' => 64, 'age_unit' => 'years', 'sex' => 'male', 'care_setting' => 'outpatient', 'information_source' => 'Patient interview and outpatient record', 'weight_kg' => 78, 'height_cm' => 170, 'vitals_status' => 'recorded', 'investigations_status' => 'recorded', 'medication_chart_status' => 'documented'],
                'profile' => [
                    'chief_complaints' => [['complaint' => 'Morning headaches', 'duration' => '3 weeks']],
                    'history_present_illness' => 'Known hypertensive for 6 years with occipital morning headaches; reports missing evening doses several times a week.',
                    'diagnoses' => [['label' => 'Essential hypertension, uncontrolled', 'type' => 'confirmed']],
                    'past_medical_history' => 'Hypertension diagnosed 6 years ago.', 'past_medical_history_none' => false,
                    'adherence_status' => 'partially_adherent', 'allergy_status' => 'no_known_allergy',
                ],
                'vitals' => [
                    ['observation_type' => 'blood_pressure', 'value_systolic' => 168, 'value_diastolic' => 98, 'unit' => 'mmHg'],
                    ['observation_type' => 'pulse', 'value_numeric' => 82, 'unit' => '/min'],
                ],
                'investigations' => [
                    ['test_name' => 'Serum creatinine', 'result_type' => 'numeric', 'result_value' => '1.0', 'unit' => 'mg/dL', 'reference_range' => '0.7–1.3', 'reported_flag' => 'normal'],
                    ['test_name' => 'Serum potassium', 'result_type' => 'numeric', 'result_value' => '4.2', 'unit' => 'mmol/L', 'reference_range' => '3.5–5.1', 'reported_flag' => 'normal'],
                ],
                'medications' => [
                    ['generic_name' => 'Amlodipine', 'indication' => 'Hypertension', 'dose_amount' => '5', 'dose_unit' => 'mg', 'dosage_form' => 'tablet', 'route' => 'oral', 'frequency' => 'OD', 'status' => 'active'],
                    ['generic_name' => 'Telmisartan', 'indication' => 'Hypertension', 'dose_amount' => '40', 'dose_unit' => 'mg', 'dosage_form' => 'tablet', 'route' => 'oral', 'frequency' => 'OD', 'status' => 'active'],
                ],
                'adr' => ['status' => 'no'],
                'counselling' => ['status' => 'performed', 'details' => ['topics' => 'Adherence to both antihypertensives, home blood pressure technique, salt restriction.']],
                'interventions' => [
                    ['details' => ['problem' => 'Missed evening doses are leaving blood pressure uncontrolled.', 'recommendation' => 'Take both antihypertensives once daily in the morning and use a dosing calendar.', 'outcome' => 'accepted']],
                ],
                'soap' => [
                    'subjective' => 'Morning headaches; admits missing doses several times a week.',
                    'objective' => 'BP 168/98 mmHg, pulse 82/min; creatinine and potassium within range.',
                    'assessment' => 'Uncontrolled essential hypertension, likely adherence-related.',
                    'plan' => 'Continue amlodipine and telmisartan, both once daily in the morning; adherence counselling.',
                    'monitoring_plan' => 'Home BP twice daily for 2 weeks; repeat creatinine and potassium in 4 weeks.',
                    'drug_related_problem_status' => 'identified', 'drug_related_problem_categories' => ['non_adherence'],
                ],
            ],
            'steps' => [
                ['action' => 'submit'],
                ['action' => 'approve', 'summary' => 'Clear assessment; the adherence intervention is well documented.'],
            ],
        ];
    }

    /** @return DemoScenario */
    private static function p2(): array
    {
        return [
            'key' => 'P2', 'student' => DemoDataset::STUDENT1, 'case_number' => 2, 'category' => 'Respiratory',
            'content' => [
                'case' => ['encounter_date' => '2026-09-12', 'age_value' => 52, 'age_unit' => 'years', 'sex' => 'male', 'care_setting' => 'inpatient', 'hospital_day_at_first_review' => 2, 'information_source' => 'Case sheet and patient interview', 'weight_kg' => 64, 'vitals_status' => 'recorded', 'investigations_status' => 'recorded', 'medication_chart_status' => 'documented'],
                'profile' => [
                    'chief_complaints' => [['complaint' => 'Worsening breathlessness', 'duration' => '4 days'], ['complaint' => 'Increased purulent sputum', 'duration' => '3 days']],
                    'history_present_illness' => 'Known COPD; increasing breathlessness with a change in sputum colour to green and a higher volume over 3 days.',
                    'diagnoses' => [['label' => 'Acute exacerbation of COPD', 'type' => 'confirmed']],
                    'past_medical_history' => 'COPD for 8 years; ex-smoker (30 pack-years).', 'past_medical_history_none' => false,
                    'allergy_status' => 'no_known_allergy',
                ],
                'vitals' => [
                    ['observation_type' => 'oxygen_saturation', 'value_numeric' => 89, 'unit' => '%'],
                    ['observation_type' => 'respiratory_rate', 'value_numeric' => 26, 'unit' => '/min'],
                ],
                'investigations' => [
                    ['test_name' => 'Total white cell count', 'result_type' => 'numeric', 'result_value' => '13.2', 'unit' => '×10³/µL', 'reference_range' => '4.0–11.0', 'reported_flag' => 'high'],
                    ['test_name' => 'Sputum Gram stain', 'result_type' => 'qualitative', 'result_value' => 'Numerous pus cells; Gram-negative bacilli', 'unit_not_stated' => true, 'reference_range_not_provided' => true, 'reported_flag' => 'not_stated'],
                ],
                'medications' => [
                    ['generic_name' => 'Salbutamol', 'indication' => 'Bronchospasm', 'dose_amount' => '2.5', 'dose_unit' => 'mg', 'dosage_form' => 'nebuliser solution', 'route' => 'inhalation', 'frequency' => 'Q6H', 'status' => 'active'],
                    ['generic_name' => 'Ipratropium', 'indication' => 'Bronchospasm', 'dose_amount' => '500', 'dose_unit' => 'mcg', 'dosage_form' => 'nebuliser solution', 'route' => 'inhalation', 'frequency' => 'Q6H', 'status' => 'active'],
                    ['generic_name' => 'Prednisolone', 'indication' => 'COPD exacerbation', 'dose_amount' => '40', 'dose_unit' => 'mg', 'dosage_form' => 'tablet', 'route' => 'oral', 'frequency' => 'OD', 'status' => 'active', 'notes' => 'Short course prescribed without stating duration.'],
                    ['generic_name' => 'Amoxicillin–clavulanate', 'indication' => 'Purulent sputum — bacterial exacerbation', 'dose_amount' => '625', 'dose_unit' => 'mg', 'dosage_form' => 'tablet', 'route' => 'oral', 'frequency' => 'TDS', 'status' => 'active'],
                ],
                'adr' => ['status' => 'no'],
                'counselling' => ['status' => 'performed', 'details' => ['topics' => 'Inhaler and spacer technique; recognising early signs of an exacerbation.']],
                'interventions' => [],
                'soap' => [
                    'subjective' => 'Breathless at rest; coughing up more green sputum.',
                    'objective' => 'SpO₂ 89% on room air, respiratory rate 26/min, white cell count 13.2.',
                    'assessment' => 'Acute exacerbation of COPD with bacterial features (purulent sputum).',
                    'plan' => 'Nebulised salbutamol and ipratropium, oral prednisolone, amoxicillin–clavulanate.',
                    'monitoring_plan' => 'Monitor clinical response.',
                    'drug_related_problem_status' => 'none_identified',
                ],
            ],
            'steps' => [
                ['action' => 'submit'],
                ['action' => 'return', 'reason' => 'The steroid course and the monitoring plan need more detail.', 'comments' => [
                    ['section' => 'medication_chart', 'body' => 'Document the intended prednisolone dose duration.', 'is_flagged' => true],
                    ['section' => 'soap', 'body' => 'Specify monitoring parameters and follow-up targets.', 'is_flagged' => true],
                ]],
                ['action' => 'correct',
                    'medications' => ['Prednisolone' => ['notes' => 'Prednisolone 40 mg once daily for 5 days.']],
                    'soap' => ['monitoring_plan' => 'Monitor SpO₂, respiratory rate, breathlessness, sputum, adverse effects and reassess after completion of the steroid course.'],
                ],
                ['action' => 'submit'],
                ['action' => 'approve', 'summary' => 'Both review comments have been addressed.'],
            ],
        ];
    }

    /** @return DemoScenario */
    private static function p3(): array
    {
        return [
            'key' => 'P3', 'student' => DemoDataset::STUDENT1, 'case_number' => 3, 'category' => 'Cardiology',
            'content' => [
                'case' => ['encounter_date' => '2026-09-18', 'age_value' => 70, 'age_unit' => 'years', 'sex' => 'female', 'care_setting' => 'inpatient', 'hospital_day_at_first_review' => 3, 'information_source' => 'Case sheet and nursing chart', 'weight_kg' => 59, 'vitals_status' => 'recorded', 'investigations_status' => 'recorded', 'medication_chart_status' => 'documented'],
                'profile' => [
                    'chief_complaints' => [['complaint' => 'Breathlessness and ankle swelling', 'duration' => '1 week']],
                    'history_present_illness' => 'Known heart failure with reduced ejection fraction; worsening orthopnoea and bilateral ankle oedema.',
                    'diagnoses' => [['label' => 'Decompensated heart failure with reduced ejection fraction', 'type' => 'confirmed']],
                    'past_medical_history' => 'Heart failure (ejection fraction 30%) for 3 years; type 2 diabetes.', 'past_medical_history_none' => false,
                    'allergy_status' => 'no_known_allergy',
                ],
                'vitals' => [
                    ['observation_type' => 'blood_pressure', 'value_systolic' => 108, 'value_diastolic' => 70, 'unit' => 'mmHg'],
                    ['observation_type' => 'pulse', 'value_numeric' => 92, 'unit' => '/min'],
                    ['observation_type' => 'weight', 'value_numeric' => 59, 'unit' => 'kg'],
                ],
                'investigations' => [
                    ['test_name' => 'Serum potassium', 'result_type' => 'numeric', 'result_value' => '5.6', 'unit' => 'mmol/L', 'reference_range' => '3.5–5.1', 'reported_flag' => 'high'],
                    ['test_name' => 'Serum creatinine', 'result_type' => 'numeric', 'result_value' => '1.5', 'unit' => 'mg/dL', 'reference_range' => '0.6–1.1', 'reported_flag' => 'high'],
                ],
                'medications' => [
                    ['generic_name' => 'Furosemide', 'indication' => 'Fluid overload', 'dose_amount' => '40', 'dose_unit' => 'mg', 'dosage_form' => 'injection', 'route' => 'intravenous', 'frequency' => 'BD', 'status' => 'active'],
                    ['generic_name' => 'Spironolactone', 'indication' => 'Heart failure', 'dose_amount' => '25', 'dose_unit' => 'mg', 'dosage_form' => 'tablet', 'route' => 'oral', 'frequency' => 'OD', 'status' => 'active'],
                    ['generic_name' => 'Bisoprolol', 'indication' => 'Heart failure', 'dose_amount' => '2.5', 'dose_unit' => 'mg', 'dosage_form' => 'tablet', 'route' => 'oral', 'frequency' => 'OD', 'status' => 'active'],
                ],
                'adr' => ['status' => 'no'],
                'counselling' => ['status' => 'planned', 'details' => ['topics' => 'Daily weights, fluid restriction and when to seek review.']],
                'interventions' => [],
                'soap' => [
                    'subjective' => 'Breathless lying flat; ankles swollen.',
                    'objective' => 'BP 108/70 mmHg, pulse 92/min, bilateral pitting oedema; potassium 5.6 mmol/L, creatinine 1.5 mg/dL.',
                    'assessment' => 'Decompensated heart failure with reduced ejection fraction, responding to diuresis.',
                    'plan' => 'Continue IV furosemide, spironolactone and bisoprolol; daily weights.',
                    'monitoring_plan' => 'Daily weight and fluid balance; potassium and creatinine every 48 hours.',
                    'drug_related_problem_status' => 'none_identified',
                ],
            ],
            'steps' => [
                ['action' => 'submit'],
                ['action' => 'approve', 'summary' => null],
                ['action' => 'reopen', 'reason' => 'Potassium of 5.6 mmol/L was not addressed while spironolactone was continued. Please revise the assessment and plan.'],
            ],
        ];
    }

    /** @return DemoScenario */
    private static function p4(): array
    {
        return [
            'key' => 'P4', 'student' => DemoDataset::STUDENT1, 'case_number' => 4, 'category' => 'Infectious Disease',
            'content' => [
                'case' => ['encounter_date' => '2026-09-22', 'age_value' => 34, 'age_unit' => 'years', 'sex' => 'female', 'care_setting' => 'outpatient', 'information_source' => 'Patient interview and laboratory report', 'weight_kg' => 58, 'vitals_status' => 'recorded', 'investigations_status' => 'recorded', 'medication_chart_status' => 'documented'],
                'profile' => [
                    'chief_complaints' => [['complaint' => 'Burning micturition and frequency', 'duration' => '2 days']],
                    'history_present_illness' => 'Dysuria, frequency and suprapubic discomfort; no fever, flank pain or vaginal discharge.',
                    'diagnoses' => [['label' => 'Uncomplicated lower urinary tract infection', 'type' => 'provisional']],
                    'past_medical_history_none' => true, 'allergy_status' => 'no_known_allergy',
                ],
                'vitals' => [
                    ['observation_type' => 'temperature', 'value_numeric' => 37.2, 'unit' => '°C'],
                    ['observation_type' => 'pulse', 'value_numeric' => 84, 'unit' => '/min'],
                ],
                'investigations' => [
                    ['test_name' => 'Urine microscopy', 'result_type' => 'qualitative', 'result_value' => 'Pus cells 15–20/hpf', 'unit_not_stated' => true, 'reference_range' => 'Pus cells 0–5/hpf', 'reported_flag' => 'high'],
                    ['test_name' => 'Urine culture', 'result_type' => 'narrative', 'result_value' => 'Escherichia coli, more than 10⁵ CFU/mL', 'unit_not_stated' => true, 'reference_range_not_provided' => true, 'reported_flag' => 'not_stated'],
                ],
                'medications' => [
                    ['generic_name' => 'Nitrofurantoin', 'indication' => 'Lower urinary tract infection', 'dose_amount' => '100', 'dose_unit' => 'mg', 'dosage_form' => 'capsule', 'route' => 'oral', 'frequency' => 'BD', 'status' => 'active', 'notes' => '5-day course.'],
                ],
                'adr' => ['status' => 'no'],
                'counselling' => ['status' => 'performed', 'details' => ['topics' => 'Complete the 5-day course, take with food, increase fluid intake.']],
                'interventions' => [],
                'soap' => [
                    'subjective' => 'Painful, frequent urination for 2 days.',
                    'objective' => 'Afebrile; urine pus cells 15–20/hpf; culture grew Escherichia coli.',
                    'assessment' => 'Uncomplicated cystitis.',
                    'plan' => 'Nitrofurantoin 100 mg twice daily for 5 days.',
                    'monitoring_plan' => 'Symptom resolution within 48–72 hours; review culture sensitivity.',
                    'drug_related_problem_status' => 'none_identified',
                ],
            ],
            'steps' => [
                ['action' => 'submit'],
                ['action' => 'return', 'reason' => 'The investigations need completing before review.', 'comments' => [
                    ['section' => 'vitals_investigations', 'body' => 'Record the urine culture sensitivity pattern, not only the organism.', 'is_flagged' => true],
                ]],
            ],
        ];
    }

    /** @return DemoScenario */
    private static function p5(): array
    {
        return [
            'key' => 'P5', 'student' => DemoDataset::STUDENT1, 'case_number' => 5, 'category' => 'Respiratory',
            'content' => [
                'case' => ['encounter_date' => '2026-10-02', 'age_value' => 23, 'age_unit' => 'years', 'sex' => 'female', 'care_setting' => 'emergency', 'information_source' => 'Patient interview and emergency record', 'weight_kg' => 55, 'vitals_status' => 'recorded', 'investigations_status' => 'recorded', 'medication_chart_status' => 'documented'],
                'profile' => [
                    'chief_complaints' => [['complaint' => 'Wheeze and chest tightness', 'duration' => '1 day']],
                    'history_present_illness' => 'Known asthmatic; symptoms worsened after a viral cold; using the reliever more than four times a day.',
                    'diagnoses' => [['label' => 'Acute exacerbation of asthma (moderate)', 'type' => 'confirmed']],
                    'past_medical_history' => 'Asthma since childhood.', 'past_medical_history_none' => false,
                    'allergy_status' => 'known_allergy', 'allergy_substance' => 'Aspirin', 'allergy_reaction' => 'Bronchospasm',
                ],
                'vitals' => [
                    ['observation_type' => 'oxygen_saturation', 'value_numeric' => 93, 'unit' => '%'],
                    ['observation_type' => 'respiratory_rate', 'value_numeric' => 24, 'unit' => '/min'],
                    ['observation_type' => 'pulse', 'value_numeric' => 108, 'unit' => '/min'],
                ],
                'investigations' => [
                    ['test_name' => 'Peak expiratory flow', 'result_type' => 'numeric', 'result_value' => '220', 'unit' => 'L/min', 'reference_range' => 'About 450 L/min predicted', 'reported_flag' => 'low'],
                ],
                'medications' => [
                    ['generic_name' => 'Salbutamol', 'indication' => 'Bronchospasm', 'dose_amount' => '5', 'dose_unit' => 'mg', 'dosage_form' => 'nebuliser solution', 'route' => 'inhalation', 'frequency' => 'Q4H', 'status' => 'active'],
                    ['generic_name' => 'Budesonide–formoterol', 'indication' => 'Asthma maintenance and reliever therapy', 'dose_amount' => '200/6', 'dose_unit' => 'mcg', 'dosage_form' => 'dry powder inhaler', 'route' => 'inhalation', 'frequency' => 'BD', 'status' => 'active'],
                    ['generic_name' => 'Prednisolone', 'indication' => 'Asthma exacerbation', 'dose_amount' => '40', 'dose_unit' => 'mg', 'dosage_form' => 'tablet', 'route' => 'oral', 'frequency' => 'OD', 'status' => 'active', 'notes' => 'For 5 days.'],
                ],
                'adr' => ['status' => 'no'],
                'counselling' => ['status' => 'performed', 'details' => ['topics' => 'Inhaler technique, written asthma action plan, avoid aspirin and other NSAIDs.']],
                'interventions' => [],
                'soap' => [
                    'subjective' => 'Wheezy and tight-chested since yesterday.',
                    'objective' => 'SpO₂ 93%, respiratory rate 24/min, pulse 108/min, peak flow 220 L/min (about 49% of predicted).',
                    'assessment' => 'Moderate acute asthma exacerbation.',
                    'plan' => 'Nebulised salbutamol, oral prednisolone for 5 days, continue budesonide–formoterol.',
                    'monitoring_plan' => 'Peak flow and SpO₂ every 4 hours; reassess before discharge, target peak flow above 75% of predicted.',
                    'drug_related_problem_status' => 'none_identified',
                ],
            ],
            'steps' => [['action' => 'submit']],
        ];
    }

    /** @return DemoScenario */
    private static function p6(): array
    {
        return [
            'key' => 'P6', 'student' => DemoDataset::STUDENT1, 'case_number' => 6, 'category' => null,
            'content' => [
                'case' => ['encounter_date' => '2026-10-08', 'age_value' => 45, 'age_unit' => 'years', 'sex' => 'male', 'care_setting' => 'inpatient', 'information_source' => 'Case sheet'],
                'profile' => [
                    'chief_complaints' => [['complaint' => 'Fever and productive cough', 'duration' => '5 days']],
                    'history_present_illness' => 'Fever with productive cough and right-sided pleuritic chest pain.',
                    'diagnoses' => [['label' => 'Community-acquired pneumonia', 'type' => 'provisional']],
                    'allergy_status' => 'no_known_allergy',
                ],
                'vitals' => [], 'investigations' => [], 'medications' => [],
                'adr' => null, 'counselling' => null, 'interventions' => [], 'soap' => null,
            ],
            'steps' => [],
        ];
    }

    /** @return DemoScenario */
    private static function r1(): array
    {
        return [
            'key' => 'R1', 'student' => DemoDataset::STUDENT2, 'case_number' => 1, 'category' => 'Cardiology',
            'content' => [
                'case' => ['encounter_date' => '2026-09-10', 'age_value' => 58, 'age_unit' => 'years', 'sex' => 'male', 'care_setting' => 'outpatient', 'information_source' => 'Patient interview and outpatient record', 'weight_kg' => 82, 'vitals_status' => 'recorded', 'investigations_status' => 'recorded', 'medication_chart_status' => 'documented'],
                'profile' => [
                    'chief_complaints' => [['complaint' => 'Chest pain on exertion', 'duration' => '2 months']],
                    'history_present_illness' => 'Central chest tightness on brisk walking, relieved by rest within 5 minutes.',
                    'diagnoses' => [['label' => 'Stable angina', 'type' => 'confirmed'], ['label' => 'Dyslipidaemia', 'type' => 'comorbidity']],
                    'past_medical_history' => 'Dyslipidaemia; current smoker.', 'past_medical_history_none' => false,
                    'allergy_status' => 'no_known_allergy',
                ],
                'vitals' => [
                    ['observation_type' => 'blood_pressure', 'value_systolic' => 132, 'value_diastolic' => 84, 'unit' => 'mmHg'],
                    ['observation_type' => 'pulse', 'value_numeric' => 72, 'unit' => '/min'],
                ],
                'investigations' => [
                    ['test_name' => 'LDL cholesterol', 'result_type' => 'numeric', 'result_value' => '162', 'unit' => 'mg/dL', 'reference_range' => 'Below 100', 'reported_flag' => 'high'],
                    ['test_name' => 'Resting ECG', 'result_type' => 'narrative', 'result_value' => 'Sinus rhythm, no acute ST changes', 'unit_not_stated' => true, 'reference_range_not_provided' => true, 'reported_flag' => 'not_stated'],
                ],
                'medications' => [
                    ['generic_name' => 'Aspirin', 'indication' => 'Secondary prevention', 'dose_amount' => '75', 'dose_unit' => 'mg', 'dosage_form' => 'tablet', 'route' => 'oral', 'frequency' => 'OD', 'status' => 'active'],
                    ['generic_name' => 'Atorvastatin', 'indication' => 'Dyslipidaemia', 'dose_amount' => '40', 'dose_unit' => 'mg', 'dosage_form' => 'tablet', 'route' => 'oral', 'frequency' => 'OD at night', 'status' => 'active'],
                    ['generic_name' => 'Isosorbide mononitrate', 'indication' => 'Angina prophylaxis', 'dose_amount' => '30', 'dose_unit' => 'mg', 'dosage_form' => 'modified-release tablet', 'route' => 'oral', 'frequency' => 'OD', 'status' => 'active'],
                ],
                'adr' => ['status' => 'no'],
                'counselling' => ['status' => 'performed', 'details' => ['topics' => 'Smoking cessation, sublingual nitrate use for chest pain, statin adherence.']],
                'interventions' => [],
                'soap' => [
                    'subjective' => 'Exertional chest tightness relieved by rest; smokes 10 cigarettes a day.',
                    'objective' => 'BP 132/84 mmHg, pulse 72/min; LDL 162 mg/dL; ECG sinus rhythm.',
                    'assessment' => 'Stable angina with uncontrolled dyslipidaemia.',
                    'plan' => 'Aspirin, atorvastatin 40 mg and isosorbide mononitrate; smoking cessation support.',
                    'monitoring_plan' => 'Angina frequency diary; repeat lipid profile in 6 weeks, target LDL below 70 mg/dL.',
                    'drug_related_problem_status' => 'none_identified',
                ],
            ],
            'steps' => [['action' => 'submit'], ['action' => 'approve', 'summary' => null]],
        ];
    }

    /** @return DemoScenario */
    private static function r2(): array
    {
        return [
            'key' => 'R2', 'student' => DemoDataset::STUDENT2, 'case_number' => 2, 'category' => 'Respiratory',
            'content' => [
                'case' => ['encounter_date' => '2026-10-05', 'age_value' => 67, 'age_unit' => 'years', 'sex' => 'male', 'care_setting' => 'outpatient', 'information_source' => 'Patient interview and spirometry report', 'weight_kg' => 61, 'vitals_status' => 'recorded', 'investigations_status' => 'recorded', 'medication_chart_status' => 'documented'],
                'profile' => [
                    'chief_complaints' => [['complaint' => 'Breathlessness on climbing stairs', 'duration' => '6 months']],
                    'history_present_illness' => 'Gradually increasing exertional breathlessness; no exacerbation this year; still smoking 5 cigarettes a day.',
                    'diagnoses' => [['label' => 'Chronic obstructive pulmonary disease, stable', 'type' => 'confirmed']],
                    'past_medical_history' => 'COPD for 5 years.', 'past_medical_history_none' => false,
                    'allergy_status' => 'no_known_allergy',
                ],
                'vitals' => [
                    ['observation_type' => 'oxygen_saturation', 'value_numeric' => 94, 'unit' => '%'],
                    ['observation_type' => 'respiratory_rate', 'value_numeric' => 18, 'unit' => '/min'],
                ],
                'investigations' => [
                    ['test_name' => 'Spirometry', 'result_type' => 'narrative', 'result_value' => 'Post-bronchodilator FEV1/FVC 0.62; FEV1 58% of predicted', 'unit_not_stated' => true, 'reference_range_not_provided' => true, 'reported_flag' => 'not_stated'],
                ],
                'medications' => [
                    ['generic_name' => 'Tiotropium', 'indication' => 'COPD maintenance', 'dose_amount' => '18', 'dose_unit' => 'mcg', 'dosage_form' => 'dry powder inhaler', 'route' => 'inhalation', 'frequency' => 'OD', 'status' => 'active'],
                    ['generic_name' => 'Salbutamol', 'indication' => 'Breathlessness relief', 'dose_amount' => '100', 'dose_unit' => 'mcg', 'dosage_form' => 'metered-dose inhaler', 'route' => 'inhalation', 'frequency' => 'As needed', 'status' => 'prn', 'prn_indication' => 'Breathlessness'],
                ],
                'adr' => ['status' => 'no'],
                'counselling' => ['status' => 'performed', 'details' => ['topics' => 'Inhaler technique check, smoking cessation, pulmonary rehabilitation referral.']],
                'interventions' => [
                    ['details' => ['problem' => 'Incorrect dry-powder inhaler technique observed.', 'recommendation' => 'Demonstrated the correct technique with teach-back; review at the next visit.', 'outcome' => 'accepted']],
                ],
                'soap' => [
                    'subjective' => 'Breathless after one flight of stairs; no recent infections.',
                    'objective' => 'SpO₂ 94%, respiratory rate 18/min; FEV1 58% of predicted.',
                    'assessment' => 'Stable COPD with an inhaler-technique problem.',
                    'plan' => 'Continue tiotropium, salbutamol as needed; correct inhaler technique; refer for pulmonary rehabilitation.',
                    'monitoring_plan' => 'Breathlessness score and inhaler technique at each visit; exacerbation count over 12 months.',
                    'drug_related_problem_status' => 'identified', 'drug_related_problem_categories' => ['administration_problem'],
                ],
            ],
            'steps' => [['action' => 'submit']],
        ];
    }
}
