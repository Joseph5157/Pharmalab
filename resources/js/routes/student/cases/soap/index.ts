import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
    applyUrlDefaults,
} from './../../../../wayfinder';
/**
 * @see \App\Http\Controllers\Student\SoapController::update
 * @see app/Http/Controllers/Student/SoapController.php:30
 * @route '/student/cases/{case}/soap'
 */
export const update = (
    args:
        | { case: string | { id: string } }
        | [caseParam: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
});

update.definition = {
    methods: ['put'],
    url: '/student/cases/{case}/soap',
} satisfies RouteDefinition<['put']>;

/**
 * @see \App\Http\Controllers\Student\SoapController::update
 * @see app/Http/Controllers/Student/SoapController.php:30
 * @route '/student/cases/{case}/soap'
 */
update.url = (
    args:
        | { case: string | { id: string } }
        | [caseParam: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { case: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { case: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            case: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        case: typeof args.case === 'object' ? args.case.id : args.case,
    };

    return (
        update.definition.url
            .replace('{case}', parsedArgs.case.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\Student\SoapController::update
 * @see app/Http/Controllers/Student/SoapController.php:30
 * @route '/student/cases/{case}/soap'
 */
update.put = (
    args:
        | { case: string | { id: string } }
        | [caseParam: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
});

/**
 * @see \App\Http\Controllers\Student\SoapController::update
 * @see app/Http/Controllers/Student/SoapController.php:30
 * @route '/student/cases/{case}/soap'
 */
const updateForm = (
    args:
        | { case: string | { id: string } }
        | [caseParam: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Student\SoapController::update
 * @see app/Http/Controllers/Student/SoapController.php:30
 * @route '/student/cases/{case}/soap'
 */
updateForm.put = (
    args:
        | { case: string | { id: string } }
        | [caseParam: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

update.form = updateForm;
const soap = {
    update: Object.assign(update, update),
};

export default soap;
