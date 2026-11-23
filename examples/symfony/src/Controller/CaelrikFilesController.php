<?php

declare(strict_types=1);

namespace App\Controller;

use Caelrik\Ui\Components\FileManager;
use Caelrik\Ui\FileManager\FileManagerConfig;
use Caelrik\Ui\FileManager\FileManagerConnector;
use Caelrik\Ui\Upload\UploadRules;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * File manager (elFinder). The files live in var/files, outside public/, and are only
 * served through the connector.
 *
 * Authorization: replace isAllowed() with your security rules, e.g.
 * $this->isGranted('ROLE_FILE_MANAGER') with symfony/security-bundle.
 */
#[Route('/caelrik/files', name: 'caelrik_files')]
final class CaelrikFilesController extends AbstractController
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/var/files')]
        private readonly string $root,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function show(): Response
    {
        if (!$this->isAllowed()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('caelrik/files.html.twig', [
            'fileManager' => FileManager::make('files')
                ->title('Shared files')
                ->url($this->generateUrl('caelrik_files_connector'))
                ->assetsUrl('/elfinder'),
        ]);
    }

    #[Route('/connector', name: '_connector', methods: ['GET', 'POST'])]
    public function connector(): never
    {
        if (!is_dir($this->root)) {
            mkdir($this->root, 0700, true);
        }

        FileManagerConnector::run(
            FileManagerConfig::local($this->root, UploadRules::images()->withType('application/pdf', ['pdf']))
                ->alias('Shared files'),
            authorized: $this->isAllowed(),
        );
    }

    /**
     * DEMO ONLY: allows everyone in the dev environment. Use real authorization.
     */
    private function isAllowed(): bool
    {
        return $this->environment === 'dev';
    }
}
