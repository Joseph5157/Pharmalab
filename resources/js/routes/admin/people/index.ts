import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
    applyUrlDefaults,
} from './../../../wayfinder';
/**
 * @see \App\Http\Controllers\Admin\PeopleController::index
 * @see app/Http/Controllers/Admin/PeopleController.php:21
 * @route '/admin/people'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
});

index.definition = {
    methods: ['get', 'head'],
    url: '/admin/people',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\Admin\PeopleController::index
 * @see app/Http/Controllers/Admin/PeopleController.php:21
 * @route '/admin/people'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\Admin\PeopleController::index
 * @see app/Http/Controllers/Admin/PeopleController.php:21
 * @route '/admin/people'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\Admin\PeopleController::index
 * @see app/Http/Controllers/Admin/PeopleController.php:21
 * @route '/admin/people'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\Admin\PeopleController::index
 * @see app/Http/Controllers/Admin/PeopleController.php:21
 * @route '/admin/people'
 */
const indexForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\Admin\PeopleController::index
 * @see app/Http/Controllers/Admin/PeopleController.php:21
 * @route '/admin/people'
 */
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\Admin\PeopleController::index
 * @see app/Http/Controllers/Admin/PeopleController.php:21
 * @route '/admin/people'
 */
indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'get',
});

index.form = indexForm;
/**
 * @see \App\Http\Controllers\Admin\PeopleController::store
 * @see app/Http/Controllers/Admin/PeopleController.php:34
 * @route '/admin/people'
 */
export const store = (
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
});

store.definition = {
    methods: ['post'],
    url: '/admin/people',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\Admin\PeopleController::store
 * @see app/Http/Controllers/Admin/PeopleController.php:34
 * @route '/admin/people'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\Admin\PeopleController::store
 * @see app/Http/Controllers/Admin/PeopleController.php:34
 * @route '/admin/people'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\PeopleController::store
 * @see app/Http/Controllers/Admin/PeopleController.php:34
 * @route '/admin/people'
 */
const storeForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\PeopleController::store
 * @see app/Http/Controllers/Admin/PeopleController.php:34
 * @route '/admin/people'
 */
storeForm.post = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
});

store.form = storeForm;
/**
 * @see \App\Http\Controllers\Admin\PeopleController::status
 * @see app/Http/Controllers/Admin/PeopleController.php:57
 * @route '/admin/people/{user}/status'
 */
export const status = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: status.url(args, options),
    method: 'patch',
});

status.definition = {
    methods: ['patch'],
    url: '/admin/people/{user}/status',
} satisfies RouteDefinition<['patch']>;

/**
 * @see \App\Http\Controllers\Admin\PeopleController::status
 * @see app/Http/Controllers/Admin/PeopleController.php:57
 * @route '/admin/people/{user}/status'
 */
status.url = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { user: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { user: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            user: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        user: typeof args.user === 'object' ? args.user.id : args.user,
    };

    return (
        status.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\Admin\PeopleController::status
 * @see app/Http/Controllers/Admin/PeopleController.php:57
 * @route '/admin/people/{user}/status'
 */
status.patch = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: status.url(args, options),
    method: 'patch',
});

/**
 * @see \App\Http\Controllers\Admin\PeopleController::status
 * @see app/Http/Controllers/Admin/PeopleController.php:57
 * @route '/admin/people/{user}/status'
 */
const statusForm = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: status.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\PeopleController::status
 * @see app/Http/Controllers/Admin/PeopleController.php:57
 * @route '/admin/people/{user}/status'
 */
statusForm.patch = (
    args:
        | { user: number | { id: number } }
        | [user: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: status.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

status.form = statusForm;
const people = {
    index: Object.assign(index, index),
    store: Object.assign(store, store),
    status: Object.assign(status, status),
};

export default people;
