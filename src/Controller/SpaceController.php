<?php

namespace Radvance\Controller;

use Radvance\Framework\BaseWebApplication as Application;
use Radvance\Model\Space;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SpaceController
{
    public function indexAction(Application $app, Request $request, $accountName)
    {
        $spaces = $app->getRepository($app->getSpaceConfig()->getTableName())->findByAccountName($accountName);

        return new Response($app['twig']->render(
            '@BaseTemplates/space/index.html.twig',
            array(
                'spaces' => $spaces,
                'accountName' => $accountName,
            )
        ));
    }
    
    public function viewAction(Application $app, Request $request, $accountName, $spaceName)
    {
        $repo = $app->getRepository($app->getSpaceConfig()->getTableName());

        $space = $repo->findByNameAndAccountName($spaceName, $accountName);

        return new Response($app['twig']->render(
            '@BaseTemplates/space/view.html.twig',
            array(
                'space' => $space
            )
        ));
    }

    public function addAction(Application $app, Request $request, $accountName)
    {
        return $this->getSpaceEditForm($app, $request, $accountName);
    }

    public function editAction(Application $app, Request $request, $accountName, $spaceName)
    {
        return $this->getSpaceEditForm($app, $request, $accountName, $spaceName);
    }

    private function getSpaceEditForm(Application $app, Request $request, $accountName, $spaceName = null)
    {
        $error = $request->query->get('error');
        $repo = $app->getRepository($app->getSpaceConfig()->getTableName());
        $add = false;
        $spaceName = trim($spaceName);

        // $space = $repo->findOneOrNullBy(array('id' => $spaceName));
        $space = $repo->findByNameAndAccountName($spaceName, $accountName);

        if (null === $space) {
            $add = true;
            $defaults = array(
                'account_name' => $accountName,
            );
            $space = new Space();
            $space->setAccountName($accountName);
        } else {
            $defaults = array(
                'account_name' => $accountName,
                'name' => $space->getName(),
                'description' => $space->getDescription(),
            );
        }


        $form = $app['form.factory']->createBuilder('form', $defaults)
            ->add('account_name', 'text', array('read_only' => true))
            ->add('name', 'text')
            ->add('description', 'textarea', array('required' => false))
            ->getForm();

        // handle form submission
        $form->handleRequest($request);
        if ($form->isValid()) {
            $data = $form->getData();
            $space->setName($data['name'])
                ->setDescription($data['description']);

            if (!$repo->persist($space)) {
                return $app->redirect(
                    $app['url_generator']->generate(
                        'space_add',
                        array(
                            'error' => 'Space exists',
                            'accountName' => $accountName,
                        )
                    )
                );
            }

            return $app->redirect(
                $app['url_generator']->generate(
                    'space_index',
                    array('accountName' => $accountName)
                )
            );
        }

        return new Response($app['twig']->render(
            '@BaseTemplates/space/edit.html.twig',
            array(
                'form' => $form->createView(),
                'space' => $space,
                'error' => $error,
                'accountName' => $accountName,
            )
        ));
    }

    public function deleteAction(Application $app, Request $request, $accountName, $spaceName)
    {
        $spaceRepository = $app->getRepository($app->getSpaceConfig()->getTableName());
        $spaceRepository->findByNameAndAccountName($spaceName, $accountName);
        if ($space) {
            $spaceRepository->remove($space);
        }

        return $app->redirect(
            $app['url_generator']->generate(
                'space_index',
                array('accountName' => $accountName)
            )
        );
    }
}