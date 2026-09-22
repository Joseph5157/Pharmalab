import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
} from './../../../wayfinder';
/**
 * @see \App\Http\Controllers\Admin\ClinicalSiteController::store
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:78
 * @route '/admin/wards'
 */
export const store = (
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
});

store.definition = {
    methods: ['post'],
    url: '/admin/wards',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\Admin\ClinicalSiteController::store
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:78
 * @route '/admin/wards'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\Admin\ClinicalSiteController::store
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:78
 * @route '/admin/wards'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\ClinicalSiteController::store
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:78
 * @route '/admin/wards'
 */
const storeForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\ClinicalSiteController::store
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:78
 * @route '/admin/wards'
 */
storeForm.post = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
});

store.form = storeForm;
const wards = {
    store: Object.assign(store, store),
};

export default wards;
