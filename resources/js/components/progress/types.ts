export type CategoryProgress = {
    category: string;
    approved: number;
    target: number | null;
};

export type AssignmentProgress = {
    assignment_id: string;
    status: 'active' | 'inactive';
    student: { id: number; name: string };
    rotation: { id: string; name: string; starts_on: string; ends_on: string };
    overall: { approved: number; target: number | null };
    categories: CategoryProgress[];
    uncategorised_approved: number;
};
