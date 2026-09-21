import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\ClinicalSiteController::index
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:22
 * @route '/admin/clinical-sites'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/clinical-sites',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\ClinicalSiteController::index
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:22
 * @route '/admin/clinical-sites'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ClinicalSiteController::index
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:22
 * @route '/admin/clinical-sites'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\ClinicalSiteController::index
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:22
 * @route '/admin/clinical-sites'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\ClinicalSiteController::index
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:22
 * @route '/admin/clinical-sites'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\ClinicalSiteController::index
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:22
 * @route '/admin/clinical-sites'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
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
                        }
                    }),
            method: 'get',
        })
    
    index.form = indexForm
/**
* @see \App\Http\Controllers\Admin\ClinicalSiteController::storeSite
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:31
 * @route '/admin/clinical-sites'
 */
export const storeSite = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeSite.url(options),
    method: 'post',
})

storeSite.definition = {
    methods: ["post"],
    url: '/admin/clinical-sites',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\ClinicalSiteController::storeSite
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:31
 * @route '/admin/clinical-sites'
 */
storeSite.url = (options?: RouteQueryOptions) => {
    return storeSite.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ClinicalSiteController::storeSite
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:31
 * @route '/admin/clinical-sites'
 */
storeSite.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeSite.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\ClinicalSiteController::storeSite
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:31
 * @route '/admin/clinical-sites'
 */
    const storeSiteForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: storeSite.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\ClinicalSiteController::storeSite
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:31
 * @route '/admin/clinical-sites'
 */
        storeSiteForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: storeSite.url(options),
            method: 'post',
        })
    
    storeSite.form = storeSiteForm
/**
* @see \App\Http\Controllers\Admin\ClinicalSiteController::storeDepartment
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:54
 * @route '/admin/departments'
 */
export const storeDepartment = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeDepartment.url(options),
    method: 'post',
})

storeDepartment.definition = {
    methods: ["post"],
    url: '/admin/departments',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\ClinicalSiteController::storeDepartment
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:54
 * @route '/admin/departments'
 */
storeDepartment.url = (options?: RouteQueryOptions) => {
    return storeDepartment.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ClinicalSiteController::storeDepartment
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:54
 * @route '/admin/departments'
 */
storeDepartment.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeDepartment.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\ClinicalSiteController::storeDepartment
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:54
 * @route '/admin/departments'
 */
    const storeDepartmentForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: storeDepartment.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\ClinicalSiteController::storeDepartment
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:54
 * @route '/admin/departments'
 */
        storeDepartmentForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: storeDepartment.url(options),
            method: 'post',
        })
    
    storeDepartment.form = storeDepartmentForm
/**
* @see \App\Http\Controllers\Admin\ClinicalSiteController::storeWard
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:78
 * @route '/admin/wards'
 */
export const storeWard = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeWard.url(options),
    method: 'post',
})

storeWard.definition = {
    methods: ["post"],
    url: '/admin/wards',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\ClinicalSiteController::storeWard
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:78
 * @route '/admin/wards'
 */
storeWard.url = (options?: RouteQueryOptions) => {
    return storeWard.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ClinicalSiteController::storeWard
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:78
 * @route '/admin/wards'
 */
storeWard.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeWard.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\ClinicalSiteController::storeWard
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:78
 * @route '/admin/wards'
 */
    const storeWardForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: storeWard.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\ClinicalSiteController::storeWard
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:78
 * @route '/admin/wards'
 */
        storeWardForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: storeWard.url(options),
            method: 'post',
        })
    
    storeWard.form = storeWardForm
const ClinicalSiteController = { index, storeSite, storeDepartment, storeWard }

export default ClinicalSiteController