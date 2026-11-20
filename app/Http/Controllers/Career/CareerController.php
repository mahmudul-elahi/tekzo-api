<?php

namespace App\Http\Controllers\Career;

use App\Http\Controllers\Controller;
use App\Models\Career\Career;
use App\Models\Page\Page;
use App\Models\Seo\Seo;
use App\Enums\StatusEnum;

class CareerController extends Controller
{
    public function index()
    {
        $careers = Career::query()
            ->where('status', StatusEnum::Active->value)
            ->orderBy('created_at', 'desc')
            ->select([
                'id',
                'heading',
                'slug',
                'vacancies',
                'type',
                'salary',
                'image',
                'qualification',
                'deadline',
                'description',
                'benefits',
                'keywords'
            ])

            ->get();
        return successResponse([
            'jobs' => $careers->map(function ($career) {
                return [
                    'id' => $career->id,
                    'heading' => $career->heading,
                    'slug' => $career->slug,
                    'vacancies' => $career->vacancies,
                    'type' => ucfirst(str_replace('_', ' ', $career->type)),
                    'salary' => $career->salary,
                    'image' => $career?->image,
                    'qualification' => $career->qualification,
                    'deadline' => $career->deadline,
                    'description' => $career->description,
                    'benefits' => array_values(
                        array_filter(
                            array_map(function ($item) {
                                return html_entity_decode(trim($item));
                            }, explode("\n", strip_tags($career->benefits)))
                        )
                    ),
                    'keywords' => $career->keywords
                ];
            }),
            'seoData' => $this->getPageSeo('career'),
        ]);
    }

    private function getPageSeo(string $slug): array
    {
        $page = Page::query()
            ->whereHas('tenant', fn($query) => $query->where('slug', config('services.tenant_slug')))
            ->where('slug', $slug)
            ->first();

        if (!$page) {
            return [];
        }

        $seo = Seo::query()
            ->where('tenant_id', $page->tenant_id)
            ->where('model_id', $page->id)
            ->where('model_type', 'App\Models\Pos\Page\Page')
            ->first();

        if (!$seo) {
            return [];
        }

        return [
            'title'        => $seo->title,
            'description'  => $seo->description,
            'tags'         => $seo->tags,
            'siteName'     => config('app.name'),
            'ogImage'      => $page->primary_logo ?? null,
            'banner'       => $page->shop_banner ?? null,
            'info_banner'  => $page->info_banner ?? null,
            'canonicalUrl' => config('services.front_base_url'),
        ];
    }

    public function show($slug)
    {
        $career = Career::query()
            ->where('slug', $slug)
            ->where('status', StatusEnum::Active->value)
            ->first();
        if (!$career) {
            return errorResponse('Career not found', 404);
        }

        return successResponse([
            'id' => $career->id,
            'heading' => $career->heading,
            'slug' => $career->slug,
            'vacancies' => $career->vacancies,
            'type' => ucfirst(str_replace('_', ' ', $career->type)),
            'salary' => $career->salary,
            'image' => $career?->image,
            'qualification' => array_values(
                array_filter(
                    array_map(function ($item) {
                        return html_entity_decode(trim($item));
                    }, explode("\n", strip_tags($career->qualification)))
                )
            ),
            'deadline' => $career->deadline,
            'description' => array_values(
                array_filter(
                    array_map(function ($item) {
                        return html_entity_decode(trim($item));
                    }, explode("\n", strip_tags($career->description)))
                )
            ),
            'benefits' => array_values(
                array_filter(
                    array_map(function ($item) {
                        return html_entity_decode(trim($item));
                    }, explode("\n", strip_tags($career->benefits)))
                )
            ),
            'keywords' => $career->keywords,

            'seoData' => [
                'title' => $career->heading,
                'description' => $career->heading,
                'tags' => $career->keywords,
                'siteName' => config('app.name'),
                'ogImage' => $career?->image ?? null,
                'canonicalUrl' => config('services.front_base_url') . '/careers/apply/' . $career->slug,
            ]
        ]);
    }
}
