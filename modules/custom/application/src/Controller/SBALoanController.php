<?php

namespace Drupal\application\Controller;




class SBALoanController {
    private $elements;

    /**
     * Create a new controller instance.
     * @return void
     */
    public function __construct(&$elements) {
        $this->elements = $elements;
    }

    public function sendToSBA() {

    }

    public function application_json_builder(array &$elements) {
        #dpm($elements);
        
        $request_data = new stdClass();
        $request_data->version = "6.0";
        
    
        $App = new stdClass();
    
        $LoanApplication = new stdClass();
        $LoanApplication->action = "insert";
        $LoanApplication->_comment = "test app #155585";
        $LoanApplication->AgentInvolved = "N";
        $LoanApplication->BusinessAgeCd = "0";
        $LoanApplication->CollateralInd = "N";
        $LoanApplication->CurrEmpQty = "0";
        $LoanApplication->EligPassiveCompanyInd = "Y";
        $LoanApplication->FrnchiseInd = "N";
        $LoanApplication->FullAmortPymtInd = "Y";
        $LoanApplication->InjectionInd = "Y";
        $LoanApplication->InterestStructureCd = "V";
        $LoanApplication->JobsCreatdQty = "1";
        $LoanApplication->JobsRetaindQty = "3";
        $LoanApplication->LenderCntctEmail = "yun@rmdslab.com";
        $LoanApplication->LenderCntctFirstName = "John";
        $LoanApplication->LenderCntctLastName = "Shen";
        $LoanApplication->LenderCntctPhnNmb = "5624490139";
        $LoanApplication->LenderCntctTitl = "CEO";
        $LoanApplication->LifeInsurRqmtInd = "Y";
        $LoanApplication->LoanBusinessEstDt = "2020-02-11 00:00:00.0";
        $LoanApplication->LoanName = "Planet Express LLC";
        $LoanApplication->LoanTermMnths = "120";
        $LoanApplication->LoanTermStartTypInd = "N";
        $LoanApplication->MnthsIntrstOnlyQty = "0";
        $LoanApplication->NAICSCd = "812210";
        $LoanApplication->NoteDt = "2020-10-26 00:00:00.0";
        $LoanApplication->ProcessingMethodCd = "7AG";
        $LoanApplication->ProjectCityName = "Huntington";
        $LoanApplication->ProjectStCd = "IN";
        $LoanApplication->ProjectStrtName1 = "100 S Park Drive";
        $LoanApplication->ProjectStrtName2 = "";
        $LoanApplication->ProjectZip4Cd = "2633";
        $LoanApplication->ProjectZipCd = "46750";
        $LoanApplication->PymtAmt = "30000.00";
        $LoanApplication->PymtTypeCode = "F";
        $LoanApplication->ReconsiderationInd = "N";
        $LoanApplication->RequestedAmt = "240000.00";
        $LoanApplication->RuralUrbanInd = "R";
        $LoanApplication->SBAGntyPct = "75.000";
        $LoanApplication->UnderwritingBy = "SBA";
        $App->LoanApplication = [$LoanApplication];
        
        $Borrower = new stdClass();
        $Borrower->action = "insert";
        $Borrower->TaxId = "0844669294";
        $Borrower->BusinessPersonInd = "B";
        $Borrower->BnkrptcyInd = "N";
        $Borrower->BooksToLenderWithinDays = "180";
        $Borrower->BusDUNSNmb = "069752764";
        $Borrower->BusinessName = $elements["business_name"]["#default_value"];
        $Borrower->BusOutstandingDebtInd = "N";
        $Borrower->BusPrimCntctNm = "Donald D Duck";
        $Borrower->ControlInterestType = "9";
        $Borrower->CurrOwnrshpEstblshDt = "Feb 11 2020 12:00AM";
        $Borrower->EPCOperatingCompnyCd = "3";
        $Borrower->ExtrnlCreditScorInd = "N";
        $Borrower->FedDisqualifiedInd = "N";
        $Borrower->GamblingOrSexualNatureInd = "N";
        $Borrower->InsurLiabInd = "Y";
        $Borrower->InsurLiabProductInd = "Y";
        $Borrower->InsurLiquorInd = "N";
        $Borrower->InsurMalpracticeInd = "N";
        $Borrower->InsurOtherInd = "N";
        $Borrower->InsurWorkersCompInd = "Y";
        $Borrower->LawsuitInd = "N";
        $Borrower->LegalOrgnztnCd = "4";
        $Borrower->MailCityName = "Huntington";
        $Borrower->MailCountryCd = "US";
        $Borrower->MailStCd = "IN";
        $Borrower->MailStrtName1 = "100 S Park Drive";
        $Borrower->MailStrtName2 = "";
        $Borrower->MailZip4Cd = "";
        $Borrower->MailZipCd = "46750";
        $Borrower->NonFedEmpInd = "Y";
        $Borrower->NonFmrSBAEmpInd = "Y";
        $Borrower->NonGS13EmpInd = "Y";
        $Borrower->NonLegBrnchEmpInd = "Y";
        $Borrower->NonSBACEmpInd = "Y";
        $Borrower->PaymentsLessThanCCInd = "";
        $Borrower->PhysCityName = "Huntington";
        $Borrower->PhysCountryCd = "US";
        $Borrower->PhysPostalCd = "";
        $Borrower->PhysStCd = "IN";
        $Borrower->PhysStrtName1 = "100 S Park Drive";
        $Borrower->PhysStrtName2 = "";
        $Borrower->PhysZip4Cd = "";
        $Borrower->PhysZipCd = "46750";
        $Borrower->PrevGovFinInd = "N";
        $Borrower->PrimaryBusinessInd = "Y";
        $Borrower->PrimaryPhone = "3013561710";
        $Borrower->PriorSBALoanInd = "N";
        $Borrower->LastName = $elements["last_name"]["#default_value"];
    
        $Borrower2 = new stdClass();
        $Borrower2->action = "insert";
        $Borrower2->TaxId = "0844692533";
        $Borrower2->BusinessPersonInd = "B";
        $Borrower2->BnkrptcyInd = "N";
        $Borrower2->BooksToLenderType = "12";
        $Borrower2->BooksToLenderWithinDays = "180";
        $Borrower2->BusinessName = "GHQ Realty LLC";
        $Borrower2->BusOutstandingDebtInd = "N";
        $Borrower2->BusPrimCntctNm = "Donald D Duck";
        $Borrower2->ControlInterestType = "7";
        $Borrower2->CurrOwnrshpEstblshDt = "Feb 12 2020 12:00AM";
        $Borrower2->EPCOperatingCompnyCd = "2";
        $Borrower2->ExtrnlCreditScorInd = "N";
        $Borrower2->FedDisqualifiedInd = "N";
        $Borrower2->GamblingOrSexualNatureInd = "N";
        $Borrower2->LawsuitInd = "N";
        $Borrower2->LegalOrgnztnCd = "4";
        $Borrower2->NonFedEmpInd = "Y";
        $Borrower2->NonFmrSBAEmpInd = "Y";
        $Borrower2->NonGS13EmpInd = "Y";
        $Borrower2->NonLegBrnchEmpInd = "Y";
        $Borrower2->NonSBACEmpInd = "Y";
        $Borrower2->PhysCityName = "Huntington";
        $Borrower2->PhysCountryCd = "US";
        $Borrower2->PhysStCd = "IN";
        $Borrower2->PhysStrtName1 = "100 S Park Drive";
        $Borrower2->PhysStrtName2 = "";
        $Borrower2->PhysZip4Cd = "";
        $Borrower2->PhysZipCd = "46750";
        $Borrower2->PrevGovFinInd = "N";
        $Borrower2->PrimaryBusinessInd = "N";
        $Borrower2->PrimaryPhone = "3013561710";
        $Borrower2->PriorSBALoanInd = "N";
        $App->Borrower = [$Borrower, $Borrower2];
        
        $BusAppr = new stdClass();
        $BusAppr->action = "insert";
        $BusAppr->Ind = "ABV";
        $App->BusAppr = [$BusAppr];
    
        $ChangeOfOwnership = new stdClass();
        $ChangeOfOwnership->action = "insert";
        $ChangeOfOwnership->BuyerEqtyBorrAmt = "0.00";
        $ChangeOfOwnership->BuyerEqtyCashAmt = "0.00";
        $ChangeOfOwnership->BuyerEqtyOthAmt = "0.00";
        $ChangeOfOwnership->Loan7aPymtAmt = "1946100.00";
        $ChangeOfOwnership->SellerFinanFullStbyAmt = "0.00";
        $ChangeOfOwnership->SellerFinanNonFullStbyAmt = "0.00";
        #$ChangeOfOwnership->TotalApprAmt = "1884000.00";
        $App->ChangeOfOwnership = [$ChangeOfOwnership];
        
        $App->Collateral = [];
        
        $CreditUnavailReasons = new stdClass();
        $CreditUnavailReasons->action = "insert";
        $CreditUnavailReasons->CreditUnavailReasonCd = "1";
        $CreditUnavailReasons->CreditUnavailReasonCommnt = "SIGNIFICANT COLLATERAL SHORTFALL";
        $App->CreditUnavailReasons = [$CreditUnavailReasons];
        
        $Eligibility = new stdClass();
        $Eligibility->action = "insert";
        $Eligibility->EligibleCd = "102";
        $Eligibility->EligibleInd = "Y";
        $App->Eligibility = [$Eligibility];
    
        $Injection = new stdClass();
        $Injection->action = "insert";
        $Injection->InjctnAmt = "108000.00";
        $Injection->InjctnOthDescTxt = "Down payment on purchase";
        $Injection->InjctnTermNotLessThanYrNmb = "";
        $Injection->InjctnTypCd = "C";
        $App->Injection = [$Injection];
        
        $Interest = new stdClass();
        $Interest->action = "insert";
        $Interest->Phase = "1";
        $Interest->AdjustPeriodCd = "Q";
        $Interest->AdjustPeriodMnths = "";
        $Interest->BaseIntrstRatePct = "4.75000";
        $Interest->BaseRateSourcTypCd = "WSJ";
        $Interest->BorrIntrstRatePct = "5.75000";
        $Interest->FirstRateAdjustDt = "2020-11-01 00:00:00.0";
        $Interest->IntrstGuaranteeInd = "F";
        $Interest->IntrstTypInd = "V";
        $Interest->ShareOfTotalMnths = "120";
        $Interest->ShareOfTotalPct = "100.000";
        $App->Interest = [$Interest];
        
        $PartnerInformation = new stdClass();
        $PartnerInformation->action = "insert";
        $PartnerInformation->LocationId = "507148";
        $App->PartnerInformation = [$PartnerInformation];
        
        $Principal = new stdClass();
        $Principal->action = "insert";
        $Principal->BusinessTaxId = "0844669294";
        $Principal->TaxId = "1306849681";
        $Principal->BusinessPersonInd = "P";
        $Principal->BirthCityName = "Wabash";
        $Principal->BirthCntryName = "USA";
        $Principal->BirthDt = "Apr 11 1980 12:00AM";
        $Principal->BirthStCd = "IN";
        $Principal->BnkrptcyInd = "N";
        $Principal->ControlInterestType = "7";
        $Principal->CreditScorSourcCd = "14";
        $Principal->EthnicCd = "HN";
        $Principal->ExtrnlCreditScorDt = "Jan 21 2020 12:00AM";
        $Principal->ExtrnlCreditScorInd = "Y";
        $Principal->ExtrnlCreditScorNmb = "790";
        $Principal->FirstName = "Donald";
        $Principal->GndrCd = "M";
        $Principal->GntyInd = "Y";
        $Principal->GntyTypCd = "1";
        $Principal->InsuranceAmt = "1100000.00";
        $Principal->InsuranceDisabInd = "N";
        $Principal->InsuranceLifeInd = "Y";
        $Principal->LastName = "Duck";
        $Principal->LawsuitInd = "N";
        $Principal->LglActnInd = "N";
        $Principal->MiddleInitial = "D";
        $Principal->NoNCAInd = "N";
        $Principal->OwnrshpInBusinessPct = "90.00";
        $Principal->PhysCityName = "Huntington";
        $Principal->PhysCountryCd = "US";
        $Principal->PhysStCd = "IN";
        $Principal->PhysStrtName1 = "142 Folk Street";
        $Principal->PhysStrtName2 = "";
        $Principal->PhysZip4Cd = "";
        $Principal->PhysZipCd = "46750";
        $Principal->Title = "Manager";
        $Principal->USCitznInd = "US";
        $Principal->VetCd = "1";
        $Principal->VetCertInd = "N";
        $App->Principal = [$Principal];
    
        $PrincipalRace = new stdClass();
        $PrincipalRace->action = "insert";
        $PrincipalRace->TaxId = "1306849681";
        $PrincipalRace->BusinessPersonInd = "P";
        $PrincipalRace->RaceCd = "7";
    
        $App->PrincipalRace = [$PrincipalRace];
    
        $SpecialPurpose = new stdClass();
        $SpecialPurpose->action = "insert";
        $SpecialPurpose->SpcPurpsLoanCd = "NOSP";
        $App->SpecialPurpose = [$SpecialPurpose];
        
        $UseOfProceeds = new stdClass();
        $UseOfProceeds->action = "insert";
        $UseOfProceeds->ProceedTypCd = "A";
        $UseOfProceeds->LoanProceedTypCd = "16";
        $UseOfProceeds->ProceedAmt = "1946100.00";
        $UseOfProceeds->PurchaseAgrmtNCAInd = "Y";
        $UseOfProceeds->PurchaseIntngblAssetAmt = "1800000.00";
        $UseOfProceeds->RefDescTxt = "Handy Example";
        
        $App->UseOfProceeds = [$UseOfProceeds];
        $request_data->App = [$App];
    
        return json_encode($request_data);
    }
}