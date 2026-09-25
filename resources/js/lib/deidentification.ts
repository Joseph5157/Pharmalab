type IdentifierPattern = { label: string; regex: RegExp };

const patterns: IdentifierPattern[] = [
    { label: 'email address', regex: /[\w.+-]+@[\w-]+\.[a-zA-Z]{2,}/ },
    { label: 'phone number', regex: /(?:\+?91[\s-]?)?[6-9]\d{9}\b/ },
    {
        label: 'long identification number (record/Aadhaar-style)',
        regex: /\b\d{6,}\b/,
    },
];

export function detectPotentialIdentifiers(
    text: string | null | undefined,
): string[] {
    if (!text) return [];

    return patterns
        .filter(({ regex }) => regex.test(text))
        .map(({ label }) => label);
}
