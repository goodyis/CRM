<?php
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\DepositQuery;
use ChurchCRM\FamilyQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\dto\SystemConfig;

// Routes search

// search for a string in Persons, families, groups, Financial Deposits and Payments
$app->get('/search/{query}', function ($request, $response, $args) {
    $query = $args['query'];
    $resultsArray = [];

    //Person Search
    try {
        array_push($resultsArray, $this->PersonService->getPersonsJSON($this->PersonService->search($query)));
    } catch (Exception $e) {
    }

    //Family Search
    try {
        array_push($resultsArray, $this->FamilyService->getFamiliesJSON($this->FamilyService->search($query)));
    } catch (Exception $e) {
    }


    //Group Search
    try {
        array_push($resultsArray, $this->GroupService->getGroupJSON($this->GroupService->search($query)));
    } catch (Exception $e) {
    }


    //Deposits Search
    if ($_SESSION['bFinance']) {

        //Envelopes Search
        if (SystemConfig::getValue('bUseDonationEnvelopes') && is_numeric($query)) {
            try {
                $q = FamilyQuery::create()
                    ->filterByEnvelope($query)
                    ->limit(5)
                    ->withColumn('fam_Name', 'displayName')
                    ->withColumn('CONCAT("' . SystemURLs::getRootPath() . 'FamilyView.php?FamilyID=",Family.Id)', 'uri')
                    ->select(['displayName', 'uri'])
                    ->find();
                array_push($resultsArray, str_replace('Families', 'Donation Envelopes', $q->toJSON()));
            } catch (Exception $ex) {
            }
        }


        try {
            $q = DepositQuery::create();
            $q->filterByComment("%$query%", Criteria::LIKE)
                ->_or()
                ->filterById($query)
                ->_or()
                ->usePledgeQuery()
                ->filterByCheckno("%$query%", Criteria::LIKE)
                ->endUse()
                ->withColumn('CONCAT("#",Deposit.Id," ",Deposit.Comment)', 'displayName')
                ->withColumn('CONCAT("' . SystemURLs::getRootPath() . 'DepositSlipEditor.php?DepositSlipID=",Deposit.Id)', 'uri')
                ->limit(5);
            array_push($resultsArray, $q->find()->toJSON());
        } catch (Exception $e) {
        }

        //Search Payments
        try {
            array_push($resultsArray, $this->FinancialService->getPaymentJSON($this->FinancialService->searchPayments($query)));
        } catch (Exception $e) {
        }
    }

    $data = ['results' => array_filter($resultsArray)];

    return $response->withJson($data);
});
