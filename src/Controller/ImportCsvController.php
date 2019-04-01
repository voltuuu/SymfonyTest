<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class ImportCsvController extends AbstractController
{
    /**
     * @Route("/import/csv", name="import_csv")
     */
    public function index()
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/ImportCsvController.php',
        ]);
    }
}
