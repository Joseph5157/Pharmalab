import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
import targets from './targets'
/**
* @see \App\Http\Controllers\Faculty\RotationTargetController::index
* @see app/Http/Controllers/Faculty/RotationTargetController.php:24
* @route '/faculty/rotations'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/faculty/rotations',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Faculty\RotationTargetController::index
* @see app/Http/Controllers/Faculty/RotationTargetController.php:24
* @route '/faculty/rotations'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Faculty\RotationTargetController::index
* @see app/Http/Controllers/Faculty/RotationTargetController.php:24
* @route '/faculty/rotations'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Faculty\RotationTargetController::index
* @see app/Http/Controllers/Faculty/RotationTargetController.php:24
* @route '/faculty/rotations'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Faculty\RotationTargetController::index
* @see app/Http/Controllers/Faculty/RotationTargetController.php:24
* @route '/faculty/rotations'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Faculty\RotationTargetController::index
* @see app/Http/Controllers/Faculty/RotationTargetController.php:24
* @route '/faculty/rotations'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Faculty\RotationTargetController::index
* @see app/Http/Controllers/Faculty/RotationTargetController.php:24
* @route '/faculty/rotations'
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

const rotations = {
    index: Object.assign(index, index),
    targets: Object.assign(targets, targets),
}

export default rotations