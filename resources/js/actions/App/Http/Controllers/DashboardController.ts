import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
} from './../../../../wayfinder';
/**
 * @see \App\Http\Controllers\DashboardController::redirect
 * @see app/Http/Controllers/DashboardController.php:19
 * @route '/dashboard'
 */
export const redirect = (
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: redirect.url(options),
    method: 'get',
});

redirect.definition = {
    methods: ['get', 'head'],
    url: '/dashboard',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\DashboardController::redirect
 * @see app/Http/Controllers/DashboardController.php:19
 * @route '/dashboard'
 */
redirect.url = (options?: RouteQueryOptions) => {
    return redirect.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\DashboardController::redirect
 * @see app/Http/Controllers/DashboardController.php:19
 * @route '/dashboard'
 */
redirect.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: redirect.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\DashboardController::redirect
 * @see app/Http/Controllers/DashboardController.php:19
 * @route '/dashboard'
 */
redirect.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: redirect.url(options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\DashboardController::redirect
 * @see app/Http/Controllers/DashboardController.php:19
 * @route '/dashboard'
 */
const redirectForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: redirect.url(options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\DashboardController::redirect
 * @see app/Http/Controllers/DashboardController.php:19
 * @route '/dashboard'
 */
redirectForm.get = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: redirect.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\DashboardController::redirect
 * @see app/Http/Controllers/DashboardController.php:19
 * @route '/dashboard'
 */
redirectForm.head = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: redirect.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'get',
});

redirect.form = redirectForm;
/**
 * @see \App\Http\Controllers\DashboardController::student
 * @see app/Http/Controllers/DashboardController.php:24
 * @route '/student'
 */
export const student = (
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: student.url(options),
    method: 'get',
});

student.definition = {
    methods: ['get', 'head'],
    url: '/student',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\DashboardController::student
 * @see app/Http/Controllers/DashboardController.php:24
 * @route '/student'
 */
student.url = (options?: RouteQueryOptions) => {
    return student.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\DashboardController::student
 * @see app/Http/Controllers/DashboardController.php:24
 * @route '/student'
 */
student.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: student.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\DashboardController::student
 * @see app/Http/Controllers/DashboardController.php:24
 * @route '/student'
 */
student.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: student.url(options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\DashboardController::student
 * @see app/Http/Controllers/DashboardController.php:24
 * @route '/student'
 */
const studentForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: student.url(options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\DashboardController::student
 * @see app/Http/Controllers/DashboardController.php:24
 * @route '/student'
 */
studentForm.get = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: student.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\DashboardController::student
 * @see app/Http/Controllers/DashboardController.php:24
 * @route '/student'
 */
studentForm.head = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: student.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'get',
});

student.form = studentForm;
/**
 * @see \App\Http\Controllers\DashboardController::faculty
 * @see app/Http/Controllers/DashboardController.php:35
 * @route '/faculty'
 */
export const faculty = (
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: faculty.url(options),
    method: 'get',
});

faculty.definition = {
    methods: ['get', 'head'],
    url: '/faculty',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\DashboardController::faculty
 * @see app/Http/Controllers/DashboardController.php:35
 * @route '/faculty'
 */
faculty.url = (options?: RouteQueryOptions) => {
    return faculty.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\DashboardController::faculty
 * @see app/Http/Controllers/DashboardController.php:35
 * @route '/faculty'
 */
faculty.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: faculty.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\DashboardController::faculty
 * @see app/Http/Controllers/DashboardController.php:35
 * @route '/faculty'
 */
faculty.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: faculty.url(options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\DashboardController::faculty
 * @see app/Http/Controllers/DashboardController.php:35
 * @route '/faculty'
 */
const facultyForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: faculty.url(options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\DashboardController::faculty
 * @see app/Http/Controllers/DashboardController.php:35
 * @route '/faculty'
 */
facultyForm.get = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: faculty.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\DashboardController::faculty
 * @see app/Http/Controllers/DashboardController.php:35
 * @route '/faculty'
 */
facultyForm.head = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: faculty.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'get',
});

faculty.form = facultyForm;
/**
 * @see \App\Http\Controllers\DashboardController::administrator
 * @see app/Http/Controllers/DashboardController.php:46
 * @route '/admin'
 */
export const administrator = (
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: administrator.url(options),
    method: 'get',
});

administrator.definition = {
    methods: ['get', 'head'],
    url: '/admin',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\DashboardController::administrator
 * @see app/Http/Controllers/DashboardController.php:46
 * @route '/admin'
 */
administrator.url = (options?: RouteQueryOptions) => {
    return administrator.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\DashboardController::administrator
 * @see app/Http/Controllers/DashboardController.php:46
 * @route '/admin'
 */
administrator.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: administrator.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\DashboardController::administrator
 * @see app/Http/Controllers/DashboardController.php:46
 * @route '/admin'
 */
administrator.head = (
    options?: RouteQueryOptions,
): RouteDefinition<'head'> => ({
    url: administrator.url(options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\DashboardController::administrator
 * @see app/Http/Controllers/DashboardController.php:46
 * @route '/admin'
 */
const administratorForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: administrator.url(options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\DashboardController::administrator
 * @see app/Http/Controllers/DashboardController.php:46
 * @route '/admin'
 */
administratorForm.get = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: administrator.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\DashboardController::administrator
 * @see app/Http/Controllers/DashboardController.php:46
 * @route '/admin'
 */
administratorForm.head = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: administrator.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'get',
});

administrator.form = administratorForm;
const DashboardController = { redirect, student, faculty, administrator };

export default DashboardController;
