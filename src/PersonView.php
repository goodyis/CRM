<?php
/*******************************************************************************
 *
 *  filename    : PersonView.php
 *  last change : 2003-04-14
 *  description : Displays all the information about a single person
 *
 *  http://www.churchcrm.io/
 *  Copyright 2001-2003 Phillip Hullquist, Deane Barker, Chris Gebhardt
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\PersonQuery;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\MailChimpService;
use ChurchCRM\Service\TimelineService;

$timelineService = new TimelineService();
$mailchimp = new MailChimpService();

// Set the page title and include HTML header

$sPageTitle = gettext('Person Profile');
require 'Include/Header.php';

// Get the person ID from the querystring
$iPersonID = FilterInput($_GET['PersonID'], 'int');

$iRemoveVO = 0;
if (array_key_exists('RemoveVO', $_GET)) {
    $iRemoveVO = FilterInput($_GET['RemoveVO'], 'int');
}

if (isset($_POST['VolunteerOpportunityAssign']) && $_SESSION['bEditRecords']) {
    $volIDs = $_POST['VolunteerOpportunityIDs'];
    if ($volIDs) {
        foreach ($volIDs as $volID) {
            AddVolunteerOpportunity($iPersonID, $volID);
        }
    }
}

// Service remove-volunteer-opportunity (these links set RemoveVO)
if ($iRemoveVO > 0 && $_SESSION['bEditRecords']) {
    RemoveVolunteerOpportunity($iPersonID, $iRemoveVO);
}

// Get this person's data
$sSQL = "SELECT a.*, family_fam.*, COALESCE(cls.lst_OptionName , 'Unassigned') AS sClassName, fmr.lst_OptionName AS sFamRole, b.per_FirstName AS EnteredFirstName, b.per_ID AS EnteredId,
				b.Per_LastName AS EnteredLastName, c.per_FirstName AS EditedFirstName, c.per_LastName AS EditedLastName, c.per_ID AS EditedId
			FROM person_per a
			LEFT JOIN family_fam ON a.per_fam_ID = family_fam.fam_ID
			LEFT JOIN list_lst cls ON a.per_cls_ID = cls.lst_OptionID AND cls.lst_ID = 1
			LEFT JOIN list_lst fmr ON a.per_fmr_ID = fmr.lst_OptionID AND fmr.lst_ID = 2
			LEFT JOIN person_per b ON a.per_EnteredBy = b.per_ID
			LEFT JOIN person_per c ON a.per_EditedBy = c.per_ID
			WHERE a.per_ID = ".$iPersonID;
$rsPerson = RunQuery($sSQL);
extract(mysqli_fetch_array($rsPerson));

$person = PersonQuery::create()->findPk($iPersonID);

if ($per_ID == $iPersonID) {

// Get the lists of custom person fields
$sSQL = 'SELECT person_custom_master.* FROM person_custom_master
			ORDER BY custom_Order';
    $rsCustomFields = RunQuery($sSQL);

// Get the custom field data for this person.
$sSQL = 'SELECT * FROM person_custom WHERE per_ID = '.$iPersonID;
    $rsCustomData = RunQuery($sSQL);
    $aCustomData = mysqli_fetch_array($rsCustomData, MYSQLI_BOTH);

// Get the Groups this Person is assigned to
$sSQL = 'SELECT grp_ID, grp_Name, grp_hasSpecialProps, role.lst_OptionName AS roleName
		FROM group_grp
		LEFT JOIN person2group2role_p2g2r ON p2g2r_grp_ID = grp_ID
		LEFT JOIN list_lst role ON lst_OptionID = p2g2r_rle_ID AND lst_ID = grp_RoleListID
		WHERE person2group2role_p2g2r.p2g2r_per_ID = '.$iPersonID.'
		ORDER BY grp_Name';
    $rsAssignedGroups = RunQuery($sSQL);
    $sAssignedGroups = ',';

// Get all the Groups
$sSQL = 'SELECT grp_ID, grp_Name FROM group_grp ORDER BY grp_Name';
    $rsGroups = RunQuery($sSQL);

// Get the volunteer opportunities this Person is assigned to
$sSQL = 'SELECT vol_ID, vol_Name, vol_Description FROM volunteeropportunity_vol
		LEFT JOIN person2volunteeropp_p2vo ON p2vo_vol_ID = vol_ID
		WHERE person2volunteeropp_p2vo.p2vo_per_ID = '.$iPersonID.' ORDER by vol_Order';
    $rsAssignedVolunteerOpps = RunQuery($sSQL);

// Get all the volunteer opportunities
$sSQL = 'SELECT vol_ID, vol_Name FROM volunteeropportunity_vol ORDER BY vol_Order';
    $rsVolunteerOpps = RunQuery($sSQL);

// Get the Properties assigned to this Person
$sSQL = "SELECT pro_Name, pro_ID, pro_Prompt, r2p_Value, prt_Name, pro_prt_ID
		FROM record2property_r2p
		LEFT JOIN property_pro ON pro_ID = r2p_pro_ID
		LEFT JOIN propertytype_prt ON propertytype_prt.prt_ID = property_pro.pro_prt_ID
		WHERE pro_Class = 'p' AND r2p_record_ID = ".$iPersonID.
  ' ORDER BY prt_Name, pro_Name';
    $rsAssignedProperties = RunQuery($sSQL);

// Get all the properties
$sSQL = "SELECT * FROM property_pro WHERE pro_Class = 'p' ORDER BY pro_Name";
    $rsProperties = RunQuery($sSQL);

// Get Field Security List Matrix
$sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 5 ORDER BY lst_OptionSequence';
    $rsSecurityGrp = RunQuery($sSQL);

    while ($aRow = mysqli_fetch_array($rsSecurityGrp)) {
        extract($aRow);
        $aSecurityType[$lst_OptionID] = $lst_OptionName;
    }

    $dBirthDate = FormatBirthDate($per_BirthYear, $per_BirthMonth, $per_BirthDay, '-', $per_Flags);

    $sFamilyInfoBegin = '<span style="color: red;">';
    $sFamilyInfoEnd = '</span>';

// Assign the values locally, after selecting whether to display the family or person information

//Get an unformatted mailing address to pass as a parameter to a google maps search
SelectWhichAddress($Address1, $Address2, $per_Address1, $per_Address2, $fam_Address1, $fam_Address2, false);
    $sCity = SelectWhichInfo($per_City, $fam_City, false);
    $sState = SelectWhichInfo($per_State, $fam_State, false);
    $sZip = SelectWhichInfo($per_Zip, $fam_Zip, false);
    $sCountry = SelectWhichInfo($per_Country, $fam_Country, false);
    $plaintextMailingAddress = getMailingAddress($Address1, $Address2, $sCity, $sState, $sZip, $sCountry);

//Get a formatted mailing address to use as display to the user.
SelectWhichAddress($Address1, $Address2, $per_Address1, $per_Address2, $fam_Address1, $fam_Address2, true);
    $sCity = SelectWhichInfo($per_City, $fam_City, true);
    $sState = SelectWhichInfo($per_State, $fam_State, true);
    $sZip = SelectWhichInfo($per_Zip, $fam_Zip, true);
    $sCountry = SelectWhichInfo($per_Country, $fam_Country, true);
    $formattedMailingAddress = getMailingAddress($Address1, $Address2, $sCity, $sState, $sZip, $sCountry);

    $sPhoneCountry = SelectWhichInfo($per_Country, $fam_Country, false);
    $sHomePhone = SelectWhichInfo(ExpandPhoneNumber($per_HomePhone, $sPhoneCountry, $dummy),
  ExpandPhoneNumber($fam_HomePhone, $fam_Country, $dummy), true);
    $sHomePhoneUnformatted = SelectWhichInfo(ExpandPhoneNumber($per_HomePhone, $sPhoneCountry, $dummy),
  ExpandPhoneNumber($fam_HomePhone, $fam_Country, $dummy), false);
    $sWorkPhone = SelectWhichInfo(ExpandPhoneNumber($per_WorkPhone, $sPhoneCountry, $dummy),
  ExpandPhoneNumber($fam_WorkPhone, $fam_Country, $dummy), true);
    $sWorkPhoneUnformatted = SelectWhichInfo(ExpandPhoneNumber($per_WorkPhone, $sPhoneCountry, $dummy),
  ExpandPhoneNumber($fam_WorkPhone, $fam_Country, $dummy), false);
    $sCellPhone = SelectWhichInfo(ExpandPhoneNumber($per_CellPhone, $sPhoneCountry, $dummy),
  ExpandPhoneNumber($fam_CellPhone, $fam_Country, $dummy), true);
    $sCellPhoneUnformatted = SelectWhichInfo(ExpandPhoneNumber($per_CellPhone, $sPhoneCountry, $dummy),
  ExpandPhoneNumber($fam_CellPhone, $fam_Country, $dummy), false);
    $sEmail = SelectWhichInfo($per_Email, $fam_Email, true);
    $sUnformattedEmail = SelectWhichInfo($per_Email, $fam_Email, false);

    if ($per_Envelope > 0) {
        $sEnvelope = $per_Envelope;
    } else {
        $sEnvelope = gettext('Not assigned');
    }

    $iTableSpacerWidth = 10;

    $bOkToEdit = ($_SESSION['bEditRecords'] ||
  ($_SESSION['bEditSelf'] && $per_ID == $_SESSION['iUserID']) ||
  ($_SESSION['bEditSelf'] && $per_fam_ID == $_SESSION['iFamID'])
); ?>
<div class="row">
  <div class="col-lg-3 col-md-3 col-sm-3">
    <div class="box box-primary">
      <div class="box-body box-profile">
        <div class="image-container">
            <img  data-name="<?= $person->getFullName()?>" data-src ="<?= SystemURLs::getRootPath().'/api/persons/'.$person->getId().'/photo' ?>" 
            class="initials-image profile-user-img img-responsive img-rounded profile-user-img-md">
            <?php if ($bOkToEdit): ?>
                <div class="after">
                <div class="buttons">
                    <a id="view-larger-image-btn" class="hide" href="#" title="<?= gettext("View Photo") ?>">
                        <i class="fa fa-search-plus"></i>
                    </a>&nbsp;
                    <a href="#" class="" data-toggle="modal" data-target="#upload-image" title="<?= gettext("Upload Photo") ?>">
                        <i class="fa fa-camera"></i>
                    </a>&nbsp;
                    <?php if ($person->isPhotoLocal()): ?>
                        <a href="#" data-toggle="modal" data-target="#confirm-delete-image" title="<?= gettext("Delete Photo") ?>">
                            <i class="fa fa-trash-o"></i>
                        </a>
                    <?php endif; ?>
                </div>
                </div>
            <?php endif; ?>
        </div>
        <h3 class="profile-username text-center">
          <?php if ($person->isMale()) {
    ?>
          <i class="fa fa-male"></i>
          <?php

} else {
    ?>
            <i class="fa fa-female"></i>
          <?php

} ?>
          <?= FormatFullName($per_Title, $per_FirstName, $per_MiddleName, $per_LastName, $per_Suffix, 0) ?></h3>

        <p class="text-muted text-center">
          <?php
          if ($sFamRole != '') {
              echo gettext($sFamRole);
          } else {
              echo gettext('Member');
          } ?>
        </p>

        <p class="text-muted text-center">
          <?= gettext($sClassName);
    if ($per_MembershipDate) {
        echo gettext(' Since:').' '.FormatDate($per_MembershipDate, false);
    } ?>
        </p>
        <?php if ($bOkToEdit) {
        ?>
          <a href="PersonEditor.php?PersonID=<?= $per_ID ?>" class="btn btn-primary btn-block"><b><?php echo gettext('Edit'); ?></b></a>
        <?php

    } ?>
      </div>
      <!-- /.box-body -->
    </div>
    <!-- /.box -->

    <!-- About Me Box -->
    <div class="box box-primary">
      <div class="box-header with-border">
        <h3 class="box-title text-center"><?php echo gettext('About Me'); ?></h3>
      </div>
      <!-- /.box-header -->
      <div class="box-body">
        <ul class="fa-ul">
          <li><i class="fa-li fa fa-group"></i><?php echo gettext('Family:'); ?> <span>
							<?php
              if ($fam_ID != '') {
                  ?>
                <a href="FamilyView.php?FamilyID=<?= $fam_ID ?>"><?= $fam_Name ?> </a>
                <a href="FamilyEditor.php?FamilyID=<?= $fam_ID ?>" class="table-link">
									<span class="fa-stack">
										<i class="fa fa-square fa-stack-2x"></i>
										<i class="fa fa-pencil fa-stack-1x fa-inverse"></i>
									</span>
                </a>
              <?php

              } else {
                  echo gettext('(No assigned family)');
              } ?>
						</span></li>
          <li><i class="fa-li glyphicon glyphicon-home"></i><?php echo gettext('Address'); ?>: <span>
						<a href="http://maps.google.com/?q=<?= $plaintextMailingAddress ?>" target="_blank">
              <?= $formattedMailingAddress ?>
            </a>
						</span></li>
          <?php if ($dBirthDate) {
                  ?>
            <li>
              <i class="fa-li fa fa-calendar"></i><?= gettext('Birth Date') ?>:
              <span><?= $dBirthDate ?></span>
              <?php if (!$person->hideAge()) {
                      ?>
              (<span data-birth-date="<?= $person->getBirthDate()->format('Y-m-d') ?>"></span> <?=FormatAgeSuffix($person->getBirthDate(), $per_Flags) ?>)
              <?php

                  } ?>
            </li>
          <?php

              }
    if (!SystemConfig::getValue('bHideFriendDate') && $per_FriendDate != '') { /* Friend Date can be hidden - General Settings */ ?>
            <li><i class="fa-li fa fa-tasks"></i><?= gettext('Friend Date') ?>: <span><?= FormatDate($per_FriendDate, false) ?></span></li>
          <?php

    }
    if ($sCellPhone) {
        ?>
            <li><i class="fa-li fa fa-mobile-phone"></i><?= gettext('Mobile Phone') ?>: <span><a href="tel:<?= $sCellPhoneUnformatted ?>"><?= $sCellPhone ?></a></span></li>
          <?php

    }
    if ($sHomePhone) {
        ?>
            <li><i class="fa-li fa fa-phone"></i><?= gettext('Home Phone') ?>: <span><a href="tel:<?= $sHomePhoneUnformatted ?>"><?= $sHomePhone ?></a></span></li>
            <?php

    }
    if ($sEmail != '') {
        ?>
            <li><i class="fa-li fa fa-envelope"></i><?= gettext('Email') ?>: <span><a href="mailto:<?= $sUnformattedEmail ?>"><?= $sEmail ?></a></span></li>
            <?php if ($mailchimp->isActive()) {
            ?>
              <li><i class="fa-li glyphicon glyphicon-send"></i>MailChimp: <span><?= $mailchimp->isEmailInMailChimp($sEmail); ?></span></li>
            <?php

        }
    }
    if ($sWorkPhone) {
        ?>
            <li><i class="fa-li fa fa-phone"></i><?= gettext('Work Phone') ?>: <span><a href="tel:<?= $sWorkPhoneUnformatted ?>"><?= $sWorkPhone ?></a></span></li>
          <?php

    } ?>
          <?php if ($per_WorkEmail != '') {
        ?>
            <li><i class="fa-li fa fa-envelope"></i><?= gettext('Work/Other Email') ?>: <span><a href="mailto:<?= $per_WorkEmail ?>"><?= $per_WorkEmail ?></a></span></li>
            <?php if ($mailchimp->isActive()) {
            ?>
              <li><i class="fa-li glyphicon glyphicon-send"></i>MailChimp: <span><?= $mailchimp->isEmailInMailChimp($per_WorkEmail); ?></span></li>
              <?php

        }
    }

          // Display the right-side custom fields
          while ($Row = mysqli_fetch_array($rsCustomFields)) {
              extract($Row);
              $currentData = trim($aCustomData[$custom_Field]);
              if ($currentData != '') {
                  if ($type_ID == 11) {
                      $custom_Special = $sPhoneCountry;
                  }
                  echo '<li><i class="fa-li glyphicon glyphicon-tag"></i>'.$custom_Name.': <span>';
                  echo nl2br((displayCustomField($type_ID, $currentData, $custom_Special)));
                  echo '</span></li>';
              }
          } ?>
        </ul>
      </div>
    </div>
      <div class="alert alert-info alert-dismissable">
          <i class="fa fa-fw fa-tree"></i> <?php echo gettext('indicates items inherited from the associated family record.'); ?>
      </div>
  </div>
  <div class="col-lg-9 col-md-9 col-sm-9">
    <div class="box box-primary box-body">
      <a class="btn btn-app" href="PrintView.php?PersonID=<?= $iPersonID ?>"><i class="fa fa-print"></i> <?= gettext("Printable Page") ?></a>
      <a class="btn btn-app" href="PersonView.php?PersonID=<?= $iPersonID ?>&AddToPeopleCart=<?= $iPersonID ?>"><i class="fa fa-cart-plus"></i> <?= gettext("Add to Cart") ?></a>
      <?php if ($_SESSION['bNotes']) {
              ?>
        <a class="btn btn-app" href="WhyCameEditor.php?PersonID=<?= $iPersonID ?>"><i class="fa fa-question-circle"></i> <?= gettext("Edit \"Why Came\" Notes") ?></a>
        <a class="btn btn-app" href="NoteEditor.php?PersonID=<?= $iPersonID ?>"><i class="fa fa-sticky-note"></i> <?= gettext("Add a Note") ?></a>
      <?php 
          }
    if ($_SESSION['bDeleteRecords']) {
        ?>
        <a class="btn btn-app bg-maroon" href="SelectDelete.php?mode=person&PersonID=<?= $iPersonID ?>"><i class="fa fa-trash-o"></i> <?= gettext("Delete this Record") ?></a>
      <?php 
    }
    if ($_SESSION['bAdmin']) {
        if (!$person->isUser()) {
            ?>
          <a class="btn btn-app" href="UserEditor.php?NewPersonID=<?= $iPersonID ?>"><i class="fa fa-user-secret"></i> <?= gettext('Make User') ?></a>
        <?php

        } else {
            ?>
          <a class="btn btn-app" href="UserEditor.php?PersonID=<?= $iPersonID ?>"><i class="fa fa-user-secret"></i> <?= gettext('Edit User') ?></a>
        <?php

        }
    } ?>
      <a class="btn btn-app" role="button" href="SelectList.php?mode=person"><i class="fa fa-list"></i> <?= gettext("List Members") ?></span></a>
    </div>
  </div>
  <div class="col-lg-9 col-md-9 col-sm-9">
    <div class="nav-tabs-custom">
      <!-- Nav tabs -->
      <ul class="nav nav-tabs" role="tablist">
        <li role="presentation" class="active"><a href="#timeline" aria-controls="timeline" role="tab" data-toggle="tab"><?= gettext('Timeline') ?></a></li>
        <li role="presentation"><a href="#family" aria-controls="family" role="tab" data-toggle="tab"><?= gettext('Family') ?></a></li>
        <li role="presentation"><a href="#groups" aria-controls="groups" role="tab" data-toggle="tab"><?= gettext('Assigned Groups') ?></a></li>
        <li role="presentation"><a href="#properties" aria-controls="properties" role="tab" data-toggle="tab"><?= gettext('Assigned Properties') ?></a></li>
        <li role="presentation"><a href="#volunteer" aria-controls="volunteer" role="tab" data-toggle="tab"><?= gettext('Volunteer Opportunities') ?></a></li>
        <li role="presentation"><a href="#notes" aria-controls="notes" role="tab" data-toggle="tab"><?= gettext('Notes') ?></a></li>
      </ul>

      <!-- Tab panes -->
      <div class="tab-content">
        <div role="tab-pane fade" class="tab-pane active" id="timeline">
          <ul class="timeline">
            <!-- timeline time label -->
            <li class="time-label">
                    <span class="bg-red">
                      <?php $now = new DateTime('');
    echo $now->format('Y-m-d') ?>
                    </span>
            </li>
            <!-- /.timeline-label -->

            <!-- timeline item -->
            <?php foreach ($timelineService->getForPerson($iPersonID) as $item) {
        ?>
              <li>
                <!-- timeline icon -->
                <i class="fa <?= $item['style'] ?>"></i>

                <div class="timeline-item">
                  <span class="time"><i class="fa fa-clock-o"></i> <?= $item['datetime'] ?></span>

                  <h3 class="timeline-header">
                    <?php if (in_array('headerlink', $item)) {
            ?>
                      <a href="<?= $item['headerlink'] ?>"><?= $item['header'] ?></a>
                    <?php

        } else {
            ?>
                      <?= $item['header'] ?>
                    <?php

        } ?>
                  </h3>

                  <div class="timeline-body">
                    <?= $item['text'] ?>
                  </div>

                  <?php if (($_SESSION['bNotes']) && ($item['editLink'] != '' || $item['deleteLink'] != '')) {
            ?>
                    <div class="timeline-footer">
                      <?php if ($item['editLink'] != '') {
                ?>
                        <a href="<?= $item['editLink'] ?>">
                          <button type="button" class="btn btn-primary"><i class="fa fa-edit"></i></button>
                        </a>
                      <?php

            }
            if ($item['deleteLink'] != '') {
                ?>
                        <a href="<?= $item['deleteLink'] ?>">
                          <button type="button" class="btn btn-danger"><i class="fa fa-trash"></i></button>
                        </a>
                      <?php

            } ?>
                    </div>
                  <?php

        } ?>
                </div>
              </li>
            <?php

    } ?>
            <!-- END timeline item -->
          </ul>
        </div>
        <div role="tab-pane fade" class="tab-pane" id="family">

          <?php if ($person->getFamId() != '') {
        ?>
          <table class="table user-list table-hover">
            <thead>
            <tr>
              <th><span><?= gettext('Family Members') ?></span></th>
              <th class="text-center"><span><?= gettext('Role') ?></span></th>
              <th><span><?= gettext('Birthday') ?></span></th>
              <th><span><?= gettext('Email') ?></span></th>
              <th>&nbsp;</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($person->getOtherFamilyMembers() as $familyMember) {
            $tmpPersonId = $familyMember->getId(); ?>
              <tr>
                <td>
                 
                 <img style="width:40px; height:40px;display:inline-block" data-name="<?= $familyMember->getFullName()?>" data-src = "<?= $sRootPath.'/api/persons/'.$familyMember->getId().'/thumbnail' ?>" class="initials-image profile-user-img img-responsive img-circle">
                  <a href="PersonView.php?PersonID=<?= $tmpPersonId ?>" class="user-link"><?= $familyMember->getFullName() ?> </a>
                  

                </td>
                <td class="text-center">
                  <?= $familyMember->getFamilyRoleName() ?>
                </td>
                <td>
                  <?= FormatBirthDate($familyMember->getBirthYear(), $familyMember->getBirthMonth(), $familyMember->getBirthDay(), '-', $familyMember->getFlags()); ?>
                </td>
                <td>
                  <?php $tmpEmail = $familyMember->getEmail();
            if ($tmpEmail != '') {
                ?>
                    <a href="#"><a href="mailto:<?= $tmpEmail ?>"><?= $tmpEmail ?></a></a>
                  <?php

            } ?>
                </td>
                <td style="width: 20%;">
                  <a href="PersonView.php?PersonID=<?= $tmpPersonId ?>&AddToPeopleCart=<?= $tmpPersonId ?>">
                    <span class="fa-stack">
                      <i class="fa fa-square fa-stack-2x"></i>
                      <i class="fa fa-cart-plus fa-stack-1x fa-inverse"></i>
                    </span>
                  </a>
                  <?php if ($bOkToEdit) {
                ?>
                    <a href="PersonEditor.php?PersonID=<?= $tmpPersonId ?>">
                      <span class="fa-stack">
                        <i class="fa fa-square fa-stack-2x"></i>
                        <i class="fa fa-pencil fa-stack-1x fa-inverse"></i>
                      </span>
                    </a>
                    <a href="SelectDelete.php?mode=person&PersonID=<?= $tmpPersonId ?>">
                      <span class="fa-stack">
                        <i class="fa fa-square fa-stack-2x"></i>
                        <i class="fa fa-trash-o fa-stack-1x fa-inverse"></i>
                      </span>
                    </a>
                  <?php

            } ?>
                </td>
              </tr>
            <?php

        } ?>
            </tbody>
          </table>
          <?php

    } ?>
        </div>
        <div role="tab-pane fade" class="tab-pane" id="groups">
          <div class="main-box clearfix">
            <div class="main-box-body clearfix">
              <?php
              //Was anything returned?
              if (mysqli_num_rows($rsAssignedGroups) == 0) {
                  ?>
                <br>
                <div class="alert alert-warning">
                  <i class="fa fa-question-circle fa-fw fa-lg"></i> <span><?= gettext('No group assignments.') ?></span>
                </div>
              <?php

              } else {
                  echo '<div class="row">';
                // Loop through the rows
                while ($aRow = mysqli_fetch_array($rsAssignedGroups)) {
                    extract($aRow); ?>
                  <div class="col-md-3">
                    <p><br/></p>
                    <!-- Info box -->
                    <div class="box box-info">
                      <div class="box-header">
                        <h3 class="box-title"><a href="GroupView.php?GroupID=<?= $grp_ID ?>"><?= $grp_Name ?></a></h3>

                        <div class="box-tools pull-right">
                          <div class="label bg-aqua"><?= $roleName ?></div>
                        </div>
                      </div>
                      <?php
                      // If this group has associated special properties, display those with values and prop_PersonDisplay flag set.
                      if ($grp_hasSpecialProps) {
                          // Get the special properties for this group
                        $sSQL = 'SELECT groupprop_master.* FROM groupprop_master WHERE grp_ID = '.$grp_ID." AND prop_PersonDisplay = 'true' ORDER BY prop_ID";
                          $rsPropList = RunQuery($sSQL);

                          $sSQL = 'SELECT * FROM groupprop_'.$grp_ID.' WHERE per_ID = '.$iPersonID;
                          $rsPersonProps = RunQuery($sSQL);
                          $aPersonProps = mysqli_fetch_array($rsPersonProps, MYSQLI_BOTH);

                          echo '<div class="box-body">';

                          while ($aProps = mysqli_fetch_array($rsPropList)) {
                              extract($aProps);
                              $currentData = trim($aPersonProps[$prop_Field]);
                              if (strlen($currentData) > 0) {
                                  $sRowClass = AlternateRowStyle($sRowClass);
                                  if ($type_ID == 11) {
                                      $prop_Special = $sPhoneCountry;
                                  }
                                  echo '<strong>'.$prop_Name.'</strong>: '.displayCustomField($type_ID, $currentData, $prop_Special).'<br/>';
                              }
                          }

                          echo '</div><!-- /.box-body -->';
                      } ?>
                      <div class="box-footer">
                        <code>
                          <?php if ($_SESSION['bManageGroups']) {
                          ?>
                            <a href="GroupView.php?GroupID=<?= $grp_ID ?>" class="btn btn-default" role="button"><i class="glyphicon glyphicon-list"></i></a>
                            <div class="btn-group">
                              <button type="button" class="btn btn-default"><?= gettext('Action') ?></button>
                              <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">
                                <span class="caret"></span>
                                <span class="sr-only">Toggle Dropdown</span>
                              </button>
                              <ul class="dropdown-menu" role="menu">
                                <li><a href="MemberRoleChange.php?GroupID=<?= $grp_ID ?>&PersonID=<?= $iPersonID ?>"><?= gettext('Change Role') ?></a></li>
                                <?php if ($grp_hasSpecialProps) {
                              ?>
                                  <li><a href="GroupPropsEditor.php?GroupID=<?= $grp_ID ?>&PersonID=<?= $iPersonID ?>"><?= gettext('Update Properties') ?></a></li>
                                <?php

                          } ?>
                              </ul>
                            </div>
                            <a href="#" onclick="GroupRemove(<?= $grp_ID.', '.$iPersonID ?>);" class="btn btn-danger" role="button"><i class="fa fa-trash-o"></i></a>
                          <?php

                      } ?>
                        </code>
                      </div>
                      <!-- /.box-footer-->
                    </div>
                    <!-- /.box -->
                  </div>
                  <?php
                  // NOTE: this method is crude.  Need to replace this with use of an array.
                  $sAssignedGroups .= $grp_ID.',';
                }
                  echo '</div>';
              }
    if ($_SESSION['bManageGroups']) {
        ?>
                <div class="alert alert-info">
                  <h4><strong><?php echo gettext('Assign New Group'); ?> </strong></h4>
                  <i class="fa fa-info-circle fa-fw fa-lg"></i> <span><?= gettext('Person will be assigned to the Group in the Default Role.') ?></span>

                  <p><br></p>
                  <select style="color:#000000" name="GroupAssignID">
                    <?php while ($aRow = mysqli_fetch_array($rsGroups)) {
            extract($aRow);

                      //If the property doesn't already exist for this Person, write the <OPTION> tag
                      if (strlen(strstr($sAssignedGroups, ','.$grp_ID.',')) == 0) {
                          echo '<option value="'.$grp_ID.'">'.$grp_Name.'</option>';
                      }
        } ?>
                  </select>
                  <a href="#" onclick="GroupAdd()" class="btn btn-success" role="button"><?= gettext('Assign User to Group') ?></a>
                  <br>
                </div>
              <?php

    } ?>
            </div>
          </div>
        </div>
        <div role="tab-pane fade" class="tab-pane" id="properties">
          <div class="main-box clearfix">
            <div class="main-box-body clearfix">
            <?php
            $sAssignedProperties = ','; ?>
            <?php if (mysqli_num_rows($rsAssignedProperties) == 0): ?>
                <br>
                <div class="alert alert-warning">
                  <i class="fa fa-question-circle fa-fw fa-lg"></i> <span><?= gettext('No property assignments.') ?></span>
                </div>
            <?php else: ?>
                <table class="table table-condensed dt-responsive" id="assigned-properties-table" width="100%">
                    <thead>
                        <tr class="TableHeader">
                            <th><?= gettext('Type') ?></th>;
                            <th><?= gettext('Name') ?></th>;
                            <th><?= gettext('Value') ?></th>;
                            <?php if ($bOkToEdit): ?>
                                <th><?= gettext('Edit') ?></th>;
                                <th><?= gettext('Remove') ?></th>';
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        //Loop through the rows
                        while ($aRow = mysqli_fetch_array($rsAssignedProperties)) {
                            $pro_Prompt = '';
                            $r2p_Value = '';
                            extract($aRow);

                            echo '<tr>';
                            echo '<td>'.$prt_Name.'</td>';
                            echo '<td>'.$pro_Name.'</td>';
                            echo '<td>'.$r2p_Value.'</td>';
                            if ($bOkToEdit) {
                                if (strlen($pro_Prompt) > 0) {
                                    echo '<td><a href="PropertyAssign.php?PersonID='.$iPersonID.'&PropertyID='.$pro_ID.'">'.gettext('Edit').'</a></td>';
                                } else {
                                    echo '<td>&nbsp;</td>';
                                }
                                echo '<td><a href="PropertyUnassign.php?PersonID='.$iPersonID.'&PropertyID='.$pro_ID.'">'.gettext('Remove').'</a></td>';
                            }
                            echo '</tr>';
                            
                            $sAssignedProperties .= $pro_ID.',';
                        } ?>
                    </tbody>
                </table>
            <?php endif; ?>

              <?php if ($bOkToEdit && mysqli_num_rows($rsProperties) != 0): ?>
                <div class="alert alert-info">
                  <div>
                    <h4><strong><?= gettext('Assign a New Property') ?>:</strong></h4>

                    <form method="post" action="PropertyAssign.php?PersonID=<?= $iPersonID ?>">
                        <div class="row">
                            <div class="form-group col-xs-12 col-md-7">
                                <select name="PropertyID" id="input-person-properties" class="form-control select2"
                                    style="width:100%" data-placeholder="Select ...">
                                <?php
                                while ($aRow = mysqli_fetch_array($rsProperties)) {
                                    extract($aRow);
                                    //If the property doesn't already exist for this Person, write the <OPTION> tag
                                    if (strlen(strstr($sAssignedProperties, ','.$pro_ID.',')) == 0) {
                                        echo '<option value="'.$pro_ID.'">'.$pro_Name.'</option>';
                                    }
                                } ?>
                                </select>
                            </div>
                            <div class="form-group col-xs-12 col-md-7">
                                <input type="submit" class="btn btn-primary" value="<?= gettext('Assign') ?>" name="Submit">
                            </div>
                        </div>
                    </form>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div role="tab-pane fade" class="tab-pane" id="volunteer">
          <div class="main-box clearfix">
            <div class="main-box-body clearfix">
              <?php

              //Initialize row shading
              $sRowClass = 'RowColorA';

    $sAssignedVolunteerOpps = ',';

              //Was anything returned?
              if (mysqli_num_rows($rsAssignedVolunteerOpps) == 0) {
                  ?>
                <br>
                <div class="alert alert-warning">
                  <i class="fa fa-question-circle fa-fw fa-lg"></i> <span><?= gettext('No volunteer opportunity assignments.') ?></span>
                </div>
              <?php

              } else {
                  echo '<table class="table table-condensed dt-responsive" id="assigned-volunteer-opps-table" width="100%">';
                  echo '<thead>';
                  echo '<tr class="TableHeader">';
                  echo '<th>'.gettext('Name').'</th>';
                  echo '<th>'.gettext('Description').'</th>';
                  if ($_SESSION['bEditRecords']) {
                      echo '<th>'.gettext('Remove').'</th>';
                  }
                  echo '</tr>';
                  echo '</thead>';
                  echo '<tbody>';

                // Loop through the rows
                while ($aRow = mysqli_fetch_array($rsAssignedVolunteerOpps)) {
                    extract($aRow);

                  // Alternate the row style
                  $sRowClass = AlternateRowStyle($sRowClass);

                    echo '<tr class="'.$sRowClass.'">';
                    echo '<td>'.$vol_Name.'</a></td>';
                    echo '<td>'.$vol_Description.'</a></td>';

                    if ($_SESSION['bEditRecords']) {
                        echo '<td><a class="SmallText" href="PersonView.php?PersonID='.$per_ID.'&RemoveVO='.$vol_ID.'">'.gettext('Remove').'</a></td>';
                    }

                    echo '</tr>';

                  // NOTE: this method is crude.  Need to replace this with use of an array.
                  $sAssignedVolunteerOpps .= $vol_ID.',';
                }
                  echo '</tbody>';
                  echo '</table>';
              } ?>

                <?php if ($_SESSION['bEditRecords'] && $rsVolunteerOpps->num_rows): ?>
                <div class="alert alert-info">
                    <div>
                        <h4><strong><?= gettext('Assign a New Volunteer Opportunity') ?>:</strong></h4>
                        
                        <form method="post" action="PersonView.php?PersonID=<?= $iPersonID ?>">
                        <div class="row">
                            <div class="form-group col-xs-12 col-md-7">
                                <select id="input-volunteer-opportunities" name="VolunteerOpportunityIDs[]" multiple 
                                    class="form-control select2" style="width:100%" data-placeholder="Select ...">
                                    <?php
                                    while ($aRow = mysqli_fetch_array($rsVolunteerOpps)) {
                                        extract($aRow);
                                      //If the property doesn't already exist for this Person, write the <OPTION> tag
                                      if (strlen(strstr($sAssignedVolunteerOpps, ','.$vol_ID.',')) == 0) {
                                          echo '<option value="'.$vol_ID.'">'.$vol_Name.'</option>';
                                      }
                                    } ?>
                                </select>
                            </div>
                            <div class="form-group col-xs-12 col-md-7">
                                <input type="submit" value="<?= gettext('Assign') ?>" name="VolunteerOpportunityAssign" class="btn btn-primary">
                            </div>
                        </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
          </div>
        </div>
        <div role="tab-pane fade" class="tab-pane" id="notes">
          <ul class="timeline">
            <!-- note time label -->
            <li class="time-label">
              <span class="bg-yellow">
                <?php echo date_create()->format('Y-m-d') ?>
              </span>
            </li>
            <!-- /.note-label -->

            <!-- note item -->
            <?php foreach ($timelineService->getNotesForPerson($iPersonID) as $item) {
                                        ?>
              <li>
                <!-- timeline icon -->
                <i class="fa <?= $item['style'] ?>"></i>

                <div class="timeline-item">
                  <span class="time"><i class="fa fa-clock-o"></i> <?= $item['datetime'] ?></span>

                  <h3 class="timeline-header">
                    <?php if (in_array('headerlink', $item)) {
                                            ?>
                      <a href="<?= $item['headerlink'] ?>"><?= $item['header'] ?></a>
                    <?php

                                        } else {
                                            ?>
                      <?= $item['header'] ?>
                    <?php

                                        } ?>
                  </h3>

                  <div class="timeline-body">
                    <?= $item['text'] ?>
                  </div>

                  <?php if (($_SESSION['bNotes']) && ($item['editLink'] != '' || $item['deleteLink'] != '')) {
                                            ?>
                    <div class="timeline-footer">
                      <?php if ($item['editLink'] != '') {
                                                ?>
                        <a href="<?= $item['editLink'] ?>">
                          <button type="button" class="btn btn-primary"><i class="fa fa-edit"></i></button>
                        </a>
                      <?php

                                            }
                                            if ($item['deleteLink'] != '') {
                                                ?>
                        <a href="<?= $item['deleteLink'] ?>">
                          <button type="button" class="btn btn-danger"><i class="fa fa-trash"></i></button>
                        </a>
                      <?php

                                            } ?>
                    </div>
                  <?php

                                        } ?>
                </div>
              </li>
            <?php

                                    } ?>
            <!-- END timeline item -->
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- Modal -->
<div id="photoUploader">
  
</div>

<div class="modal fade" id="confirm-delete-image" tabindex="-1" role="dialog" aria-labelledby="delete-Image-label" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h4 class="modal-title" id="delete-Image-label"><?= gettext('Confirm Delete') ?></h4>
      </div>

      <div class="modal-body">
        <p><?= gettext('You are about to delete the profile photo, this procedure is irreversible.') ?></p>

        <p><?= gettext('Do you want to proceed?') ?></p>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal"><?= gettext("Cancel") ?></button>
        <button class="btn btn-danger danger" id="deletePhoto"><?= gettext("Delete") ?></button>
      </div>
    </div>
  </div>
</div>
<script src="<?= SystemURLs::getRootPath() ?>/skin/jquery-photo-uploader/PhotoUploader.js" type="text/javascript"></script>
<link href="<?= SystemURLs::getRootPath() ?>/skin/jquery-photo-uploader/PhotoUploader.css" rel="stylesheet">
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/MemberView.js" type="text/javascript"></script>
<script>
  var person_ID = <?= $iPersonID ?>;
  function GroupRemove(Group, Person) {
    var answer = confirm("<?= gettext('Are you sure you want to remove this person from the Group') ?>");
    if (answer)
      $.ajax({
        method: "POST",
        data:{"_METHOD":"DELETE"},
        url: window.CRM.root + "/api/groups/" + Group + "/removeuser/" + Person
      }).done(function (data) {
        location.reload();
      });
  }

  function GroupAdd() {
    var GroupAssignID = $("select[name='GroupAssignID'] option:selected").val();
    $.ajax({
      method: "POST",
      url: window.CRM.root + "/api/groups/" + GroupAssignID + "/adduser/" + person_ID
    }).done(function (data) {
      location.reload();
    });
  }

  $("#deletePhoto").click (function () {
    $.ajax({
    type: "POST",
    url: window.CRM.root + "/api/persons/<?= $iPersonID ?>/photo",
    encode: true,
    dataType: 'json',
    data: { 
      "_METHOD": "DELETE"
    }
    }).done(function(data) {
      location.reload();
    });
  });

  window.CRM.photoUploader =  $("#photoUploader").PhotoUploader({
    url: window.CRM.root + "/api/persons/<?= $iPersonID ?>/photo",
    maxPhotoSize: window.CRM.maxUploadSize,
    photoHeight: 400,
    photoWidth: 400,
    done: function(e) {
      window.location.reload();
    }
  });

  $("#uploadImageButton").click(function(){
    window.CRM.photoUploader.show();
  });
  

    $(document).ready(function() {
        $("#input-volunteer-opportunities").select2();
        $("#input-person-properties").select2();
        
        var options = {
            "language": {
                "url": window.CRM.root + "/skin/locale/datatables/" + window.CRM.locale + ".json"
            },
            "responsive": true
        };
        $("#assigned-volunteer-opps-table").DataTable(options);
        $("#assigned-properties-table").DataTable(options);
        
        
        contentExists(window.CRM.root + "/api/persons/" + person_ID + "/photo", function(success) {
            if (success) {
                $("#view-larger-image-btn").removeClass('hide');
                 
                $("#view-larger-image-btn").click(function() {
                    bootbox.alert({
                        title: "<?= gettext('Photo') ?>",
                        message: '<img class="img-rounded img-responsive center-block" src="<?= SystemURLs::getRootPath() ?>/api/persons/' + person_ID + '/photo" />',
                        backdrop: true
                    });
                });
            }
        });
        

        
        
    });
  
  
</script>

<?php

} else {
    ?>
  <div class="error-page">
    <h2 class="headline text-yellow">404</h2>

    <div class="error-content">
      <h3><i class="fa fa-warning text-yellow"></i><?= gettext('Oops! Person not found.') ?></h3>

      <p>
      	<?= gettext('We could not find the person you were looking for.<br>Meanwhile, you may')?> <a href="//MembersDashboard.php"><?= gettext('return to member dashboard') ?></a>
      </p>
    </div>
  </div>
  <?php

}
require 'Include/Footer.php' ?>
