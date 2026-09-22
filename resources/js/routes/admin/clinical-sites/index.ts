import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
} from './../../../wayfinder';
/**
 * @see \App\Http\Controllers\Admin\ClinicalSiteController::index
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:22
 * @route '/admin/clinical-sites'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
});

index.definition = {
    methods: ['get', 'head'],
    url: '/admin/clinical-sites',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\Admin\ClinicalSiteController::index
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:22
 * @route '/admin/clinical-sites'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\Admin\ClinicalSiteController::index
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:22
 * @route '/admin/clinical-sites'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\Admin\ClinicalSiteController::index
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:22
 * @route '/admin/clinical-sites'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\Admin\ClinicalSiteController::index
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:22
 * @route '/admin/clinical-sites'
 */
const indexForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\Admin\ClinicalSiteController::index
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:22
 * @route '/admin/clinical-sites'
 */
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\Admin\ClinicalSiteController::index
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:22
 * @route '/admin/clinical-sites'
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
 * @see \App\Http\Controllers\Admin\ClinicalSiteController::store
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:31
 * @route '/admin/clinical-sites'
 */
export const store = (
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
});

store.definition = {
    methods: ['post'],
    url: '/admin/clinical-sites',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\Admin\ClinicalSiteController::store
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:31
 * @route '/admin/clinical-sites'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\Admin\ClinicalSiteController::store
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:31
 * @route '/admin/clinical-sites'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\ClinicalSiteController::store
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:31
 * @route '/admin/clinical-sites'
 */
const storeForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\ClinicalSiteController::store
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:31
 * @route '/admin/clinical-sites'
 */
storeForm.post = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
});

store.form = storeForm;
const clinicalSites = {
    index: Object.assign(index, index),
    store: Object.assign(store, store),
};

export default clinicalSites;
