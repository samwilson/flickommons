<?php
declare(strict_types = 1);

namespace App\Controller;

use App\Flickr;
use Krinkle\Intuition\Intuition;
use OOUI\ButtonInputWidget;
use OOUI\FieldLayout;
use OOUI\TextInputWidget;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\ItemInterface;

class FlickrDupesController extends ControllerBase
{

    /**
     * @Route("/flickr/dupes", name="flickr_dupes")
     */
    public function main(Request $request, Flickr $flickr, Session $session, Intuition $intuition): Response
    {
        $paramsForRedirect = [];
        if ($request->get('id1')) {
            $paramsForRedirect['id1'] = $request->get('id1');
        }
        if ($request->get('id2')) {
            $paramsForRedirect['id2'] = $request->get('id2');
        }
        if (2 === count($paramsForRedirect)) {
            return $this->redirectToRoute('flickr_dupes_compare', $paramsForRedirect);
        }

        $photo1Field = new FieldLayout(
            new TextInputWidget(['name' => 'id1']),
            ['label' => $this->msg('flickr-dupes-photo1')]
        );
        $photo2Field = new FieldLayout(
            new TextInputWidget(['name' => 'id2']),
            ['label' => $this->msg('flickr-dupes-photo2')]
        );

        $alerts = [];
        if (!$flickr->getUserId()) {
            $alerts[] = [
                'type'=> 'warning',
                'message' => $this->msg('flickr-not-logged-in'),
            ];
        }

        return $this->render('flickr_dupes.html.twig', [
            'alerts' => $alerts,
            'page_name' => 'flickr_dupes',
            'flickr_logged_in' => $flickr->getUserId(),
            'photo1' => $photo1Field,
            'photo2' => $photo2Field,
            'dupesCompareButton' => new ButtonInputWidget([
                'label' => $this->msg('flickr-dupes-compare-button'),
                'type' => 'submit',
            ]),
            'dupesFindButton' => new ButtonInputWidget([
                'label' => $this->msg('flickr-dupes-find-button'),
                'type' => 'submit',
            ]),
        ]);
    }

    /**
     * Find the next set of duplicates.
     * @Route("/flickr/dupes/find", name="flickr_dupes_find")
     */
    public function find(Flickr $flickr): Response
    {
        if (!$flickr->getUserId()) {
            return $this->redirectToRoute('flickr_dupes');
        }
        // This gets *all* tags, which might sound inefficient but there doesn't seem to be a way to page through them.
        $cache = new FilesystemAdapter('app.cache');
        $cacheKey = 'flickr-tags';
        $tags = $cache->get($cacheKey, function (ItemInterface $item) use ($flickr) {
            $item->expiresAfter(60 * 30);
            $tags = $flickr->getFlickr()->tags_getListUser();
            $filteredTags = array_filter($tags, function ($tag) {
                // Only include checksum tags.
                if ('checksum:' === substr($tag, 0, strlen('checksum:'))) {
                    return $tag;
                }
                return false;
            });
            return $filteredTags;
        });

        foreach ($tags as $tagIndex => $tag) {
            // Find photos with this tag.
            $search = $flickr->getFlickr()->photos()->search(['tags' => $tag]);
            // If there are multiple, redirect to the comparison page.
            if ($search['total'] > 1) {
                return $this->redirectToRoute('flickr_dupes_compare', [
                    'id1' => $search['photo'][0]['id'],
                    'id2' => $search['photo'][1]['id'],
                ]);
            }
            // If there's only one search result, remove this tag from the cache.
            $cache->delete($cacheKey);
            $cache->get($cacheKey, function (ItemInterface $item) use ($tags, $tagIndex) {
                $item->expiresAfter(60 * 30);
                unset($tags[$tagIndex]);
                return $tags;
            });
        }
    }

    /**
     * Compare two FLickr photos and provide the means of deleting one.
     * @Route(
     *     "/flickr/dupes/{id1}/{id2}",
     *     name="flickr_dupes_compare",
     *     requirements={"id1"="\d+", "id2"="\d+"}
     *     )
     */
    public function compare(Flickr $flickr, Session $session, string $id1, string $id2): Response
    {
        if (!$flickr->getUserId()) {
            return $this->redirectToRoute('flickr_dupes');
        }
        $photos = [];
        foreach ([1 => $id1, 2 => $id2] as $id) {
            $photos[$id] = $flickr->getInfo((int)$id, true);
            $photos[$id]['deleteButton'] = new ButtonInputWidget([
                'label' => $this->msg('flickr-dupes-delete-button'),
                'value' => $id,
                'name' => 'id',
                'type' => 'submit',
                'flags' => 'destructive',
            ]);
        }

        return $this->render('flickr_compare.html.twig', [
            'flickr_logged_in' => $session->has(FlickrAuthController::SESSION_KEY),
            'photos' => $photos,
        ]);
    }

    /**
     * Delete the given Flickr photo.
     * @Route( "/flickr/dupes/delete", name="flickr_dupes_delete", methods="POST")
     */
    public function delete(Request $request, Flickr $flickr): Response
    {
        $id = (int)$request->get('id');
        $flickr->getFlickr()->photos_delete($id);
        return $this->redirectToRoute('flickr_dupes');
    }
}
