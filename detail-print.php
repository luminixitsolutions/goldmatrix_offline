<!DOCTYPE>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<title>Deshmukh Eye Hospital</title>
<style type="text/css">
body {
	margin-left: 20px;
	margin-top: 5px;
	margin-right: 5px;
	margin-bottom: 2px;
}
</style>
<style>
.tblborder {
  border: 1px solid #ada9a9;
  border-collapse: collapse;
}
.alltextfont {
	font-family: Poppins;
	font-size: 11px;
	font-style: normal;
	line-height: normal;
	font-weight: normal;
	font-variant: normal;
	
	color: #4C4C4C;
}
.smalltextfont {
	font-family: Poppins;
	font-size: 9px;
	font-style: normal;
	line-height: normal;
	font-weight: normal;
	font-variant: normal;
	
	color: #000000;
}
.addrfont {
	font-family: "Poppins Light";
	font-size: 9pt;
	font-style: normal;
	line-height: normal;
	font-weight: normal;
	font-variant: normal;
	
	color: #000000;
}
.tophdfnt {
	font-family: "Poppins Medium";
	font-size: 14pt;
	font-style: normal;
	line-height: normal;
	font-weight: normal;
	font-variant: normal;

	color: #000000;
	letter-spacing: 0.3mm;
}
 .thbrd{
  border: 1px solid #ada9a9;
  border-collapse: collapse;
}

 .tdbrd{
	border: 1px solid #ada9a9;
	border-collapse: collapse;
	font-family: "Poppins Medium";
	font-size: 8pt;
	font-style: normal;
	line-height: normal;
	font-weight: normal;
	font-variant: normal;
	color: #424242;
	word-spacing: normal;
	letter-spacing: 0.2mm;
}
.txtfundus{
	
	border-collapse: collapse;
	font-family: "Poppins Medium";
	font-size: 8pt;
	font-style: normal;
	line-height: normal;
	font-weight: normal;
	font-variant: normal;
	color: #4C4C4C;
	word-spacing: normal;
	letter-spacing: 0.2mm;
}
.txtallvalues{
	border: 1px solid #ada9a9;
	border-collapse: collapse;
	font-family: "Poppins Light";
	font-size: 8pt;
	font-style: normal;
	line-height: normal;
	font-weight: normal;
	font-variant: normal;
	color: #424242;
	word-spacing: normal;
	
}
.txtdegree{
	
	border-collapse: collapse;
	font-family: "Poppins Light";
	font-size: 8pt;
	font-style: normal;
	line-height: normal;
	font-weight: normal;
	font-variant: normal;
	color: #424242;
	word-spacing: normal;
	
}
.txtallheading {
	font-family: "Poppins SemiBold";
	font-size: 9pt;
	font-style: normal;
	line-height: normal;
	font-weight: normal;
	font-variant: normal;
	
	color: #000000;
	
}
.txtdrname {
	font-family: "Poppins Light";
	font-size: 9pt;
	font-style: normal;
	line-height: normal;
	font-weight: normal;
	font-variant: normal;
	
	color: #000000;
	
}
.txtnabhlogo {
	font-family: "Poppins Medium";
	font-size: 7pt;
	font-style: normal;
	line-height: normal;
	font-weight: normal;
	font-variant: normal;
	letter-spacing: 0.2mm
	color: #000000;
	letter-spacing: 0.3mm;
}
.tdbrd1 {border: 1px solid #ada9a9;
  border-collapse: collapse;
  font-family: Poppins;
	font-size: 11px;
	font-style: normal;
	line-height: normal;
	font-weight: normal;
	font-variant: normal;
	
	color: #4C4C4C;
}
.style1 {
	border: 1px solid #ada9a9;
	border-collapse: collapse;
	font-family: Poppins;
	font-size: 11px;
	font-style: normal;
	line-height: normal;
	font-weight: normal;
	font-variant: normal;
	color: #4C4C4C;
	letter-spacing: 0.2mm;
}
.style3 {font-size: 10}
.singleline {
	border-left-width: 1px;
	border-left-style: solid;
	border-left-color: #ada9a9;
}

@media print {
        @page {
            margin: 15mm;
            size: A4;
        }

        @page :footer {
            display: none;
        }

        .noPrint {
            display: none !important;
        }

        @page :header {
            display: none;
        }

        body {
            margin: 0;
            padding: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* Main content: avoid overlap with fixed footer */
        .main-print-content {
            padding-bottom: 90px;
        }
    }

    @media print {
        a[href]:after {
            content: none !important;
        }
    }

    @media screen {
        div.divFooter {
            display: none !important;
        }
    }

    @media print {
        div.divFooter {
            display: block !important;
            position: fixed;
            left: 0;
            right: 0;
            bottom: 15px;
            width: 100%;
            margin: 0;
        }
        div.divFooter.detail-print-footer table {
            margin: 0 auto;
        }
    }
    .txtallvalues1 {border: 1px solid #ada9a9;
	border-collapse: collapse;
	font-family: "Poppins Light";
	font-size: 8pt;
	font-style: normal;
	line-height: normal;
	font-weight: normal;
	font-variant: normal;
	color: #424242;
	word-spacing: normal;
}

.ocexamfont{
  font-size: 9pt;
  font-family: "Poppins semibold";
  color: #404040;
}
</style>
</head>
<script>
    //window.print();
    //window.onafterprint = window.close;
    </script>
    @php
    use App\Http\Controllers\ConsultantController;
    use App\Http\Controllers\OptoController;
    @endphp
<body>
<table width="700" border="0" cellspacing="1" cellpadding="1" class="main-print-content" align="center">
<tr class="noPrint">
            <td style="float:right;"><button align="center" style="text-align:center;font-size: 18px;width: 100px;"
                    onclick="window.print();" class="noPrint">Print</button>            </td>
        </tr>

        <tr>
          <td align="left" valign="top"><table width="100%" border="0" cellspacing="0" cellpadding="0" >
            <tr>
              <td width="35%" align="center" valign="middle" ><img src="{{url('logo.jpg')}}"></td>
              <td width="39%" align="center" valign="middle"><span class="txtdegree"><strong>Khaparde Garden, Near Irwin Square,<br>
                Netradan Road,
                Amravati -444601<br>
                Ph:(0721) 2663151
                | Mob :
                8698249356</strong><br>
                website : www.deshmukheyehospital.com</span></td>
              <td width="26%" align="center" valign="middle"><img src="{{url('nabh.jpg')}}"></td>
            </tr>
          </table></td>
        </tr>    
  
  <tr>
  <tr>
  	<td align="center" valign="top" class="txtallheading"><strong>
		Medical Report</strong>	</td>
  </tr>
    <td align="left" valign="top"><table width="100%" border="0" cellpadding="4" cellspacing="0" class="tblborder">
      <tr style="border:1px; color:#333333">
        <td width="21%" align="left" valign="middle" class="tdbrd">Name</td>
        <td width="39%" align="left" valign="middle" class="txtallheading">{{strtoupper(strtolower($pt_data['items']['PatientName']))}}</td>
        <td width="10%" align="left" valign="middle" class="tdbrd">MRD No</td>
        <td width="10%" align="center" valign="middle" class="txtallheading">{{$pt_data['items']['MrdNo']}}</td>
        <td width="7%" align="center" valign="middle" class="tdbrd">Date</td>
        <td width="13%" align="center" valign="middle" class="txtallvalues">{{date("d/m/Y", strtotime(str_replace('-', '/',$opto_data['items']['VisitDate'])))}}</td>
      </tr>
      <tr>
        <td align="left" valign="middle" class="tdbrd">Address</td>
        <td colspan="2" align="left" valign="middle" class="txtallvalues">{{strtoupper(strtolower($pt_data['items']['Address']))}}</td>
        <td colspan="2" align="center" valign="middle" class="tdbrd">Mobile </td>
        <td align="center" valign="middle" class="txtallvalues">{{$pt_data['items']['Phone']}}</td>
      </tr>
    </table></td>
  </tr>
  
  <tr>
    <td align="left" valign="top" ><table width="100%" border="0" cellspacing="0" cellpadding="4" class="tdbrd1">
      <tr>
        <td width="21%" class="tdbrd" >Complaints</td>
        <td width="79%" class="txtallvalues" style="font-size: 9pt;font-family: 'Poppins semibold';">{{$opto_data['items']['Complaint1']}}
                            <br>
                            {{$opto_data['items']['Complaint2']}}  <br>                                         </td>
      </tr>
    </table></td>
  </tr>

  <tr>
    <td align="left" valign="top" ><table width="100%" border="0" cellspacing="0" cellpadding="4" class="tdbrd1">
      <tr>
        <td width="21%" align="left" valign="top" class="tdbrd">Ocular History</td>
        <td width="79%" align="left" valign="top" class="txtallvalues">{{$opto_data['items']['PastHistory']}}</td>
      </tr>
    </table></td>
  </tr>

  <tr>
  <tr>
  <td><table width="100%" border="0" cellspacing="0" cellpadding="0">
    <tr>
      <td width="71%" align="left" valign="top" class="txtallheading"><strong>AR / KERATOMETRY </strong></td>
      <td width="29%" align="left" valign="top" class="txtallheading"><strong>PREV. Glasses</strong></td>
      </tr>
  </table></td>
  </tr>
    <td align="left" valign="top" ><table width="100%" border="0" cellspacing="0" cellpadding="0">
      <tr>
        <td align="left" valign="top"><table width="100%" border="0" cellspacing="0" cellpadding="4" class="tblborder">
          <tr>
            <td width="9%" align="center" valign="top" class="txtallvalues1" >&nbsp;</td>
            <td width="11%" align="center" valign="top" class="txtallvalues1">SPH</td>
            <td width="11%" align="center" valign="top" class="txtallvalues1">CYL</td>
            <td width="11%" align="center" valign="top" class="txtallvalues1">AXIS</td>
            <td width="11%" align="center" valign="top" class="txtallvalues1">K1</td>
            <td width="11%" align="center" valign="top" class="txtallvalues1">AXIS</td>
            <td width="11%" align="center" valign="top" class="txtallvalues1">K2</td>
            <td width="11%" align="center" valign="top" class="txtallvalues1">AXIS</td>
          </tr>
          <tr>
            <td width="9%" align="center" valign="top" class="txtallvalues1" >RE</td>
            <td width="13%" align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['ArSrSphRe']}}</td>
            <td width="11%" align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['ArSrCylRe']}}</td>
            <td width="13%" align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['ArSrAxisRe']}}</td>
            <td width="13%" align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['RefK1Re']}}</td>
            <td width="13%" align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['RefK1AxisRe']}}</td>
            <td width="14%" align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['RefK2Re']}}</td>
            <td width="14%" align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['RefK2AxisRe']}}</td>
          </tr>
          <tr>
            <td align="center" valign="top" class="txtallvalues1" >LE</td>
            <td align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['ArSrSphLe']}}</td>
            <td align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['ArSrCylLe']}}</td>
            <td align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['ArSrAxisLe']}}</td>
            <td align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['RefK1Le']}}</td>
            <td align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['RefK1AxisLe']}}</td>
            <td align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['RefK2Le']}}</td>
            <td align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['RefK2AxisLe']}}</td>
          </tr>

        </table></td>
        <td width="30%" align="right" valign="top"><table width="100%" border="0" cellspacing="0" cellpadding="4" class="tdbrd1">
          <tr>
            <td align="center" valign="top" class="txtallvalues">&nbsp;</td>
            <td align="center" valign="top" class="txtallvalues">SPH</td>
            <td align="center" valign="top" class="txtallvalues">CYL</td>
            <td align="center" valign="top" class="txtallvalues">AXIS</td>
            <td align="center" valign="top" class="txtallvalues">ADD</td>
            </tr>
          <tr>
            <td align="center" valign="top" class="tdbrd">&nbsp;</td>
            <td width="16%" align="center" valign="top" class="tdbrd">{{$opto_data['items']['PgSphRe']}}</td>
          <td width="24%" align="center" valign="top" class="tdbrd">{{$opto_data['items']['PgCylRe']}}</td>
          <td width="21%" align="center" valign="top" class="tdbrd">{{$opto_data['items']['PgAxisRe']}}</td>
          <td width="22%" align="center" valign="top" class="tdbrd">{{$opto_data['items']['PgNearAddRe']}}</td>
          
              </tr>
          <tr>
            <td align="center" valign="top" class="tdbrd">&nbsp;</td>
            <td align="center" valign="top" class="tdbrd">{{$opto_data['items']['PgSphLe']}}</td>
            <td align="center" valign="top" class="tdbrd">{{$opto_data['items']['PgCylLe']}}</td>
            <td align="center" valign="top" class="tdbrd">{{$opto_data['items']['PgAxisLe']}}</td>
            <td align="center" valign="top" class="tdbrd">{{$opto_data['items']['PgNearAddLe']}}</td>
          </tr>
        </table></td>
        </tr>
    </table></td>
  
  </tr>
    

  <tr>
    <td align="left" valign="top" class="txtallheading"><div align="left">
      <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
          <td width="19%" class="txtallheading"><strong>Refraction &amp; Glasses</strong></td>
          <td width="33%" class="txtfundus"><div align="center"></div></td>
          <td width="48%" class="txtfundus"><div align="center"></div></td>
        </tr>
      </table>
    </div></td>
  </tr>
  <tr>
    <td align="left" valign="top"><table width="100%" border="0" cellspacing="2" cellpadding="0">
      <tr>
        <td width="65%" align="left" valign="top"><table width="100%" border="0" cellspacing="0" cellpadding="4" class="tblborder">
          <tr>
            <td width="10%" align="center" valign="top" class="txtallvalues1" >&nbsp;</td>
            <td width="11%" align="center" valign="top" class="txtallvalues1">SPH</td>
            <td width="11%" align="center" valign="top" class="txtallvalues1">CYL</td>
            <td width="11%" align="center" valign="top" class="txtallvalues1">AXIS</td>
            <td width="11%" align="center" valign="top" class="txtallvalues1">ADD</td>
            <td width="11%" align="center" valign="top" class="txtallvalues1">VISION</td>
            <td width="11%" align="center" valign="top" class="txtallvalues1">NEAR</td>
            {{-- <td width="11%" align="center" valign="top" class="txtallvalues1">PIN HOLE </td> --}}
          </tr>
		   <tr>
            <td width="10%" align="center" valign="top" class="txtallvalues1" >RE</td>
            <td width="11%" align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['SrSphRe']}}</td>
            <td width="11%" align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['SrCylRe']}}</td>
            <td width="11%" align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['SrAxisRe']}}</td>
            <td width="11%" align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['SrNearAddRe']}}</td>
            <td width="11%" align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['SrVaGlassRe']}}</td>
            <td width="11%" align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['SrNvRe']}}</td>
            {{-- <td width="11%" align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['PgNearAddLe']}}</td> --}}
          </tr>
		   <tr>
		     <td align="center" valign="top" class="txtallvalues1" >LE</td>
		     <td align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['SrSphLe']}}</td>
		     <td align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['SrCylLe']}}</td>
		     <td align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['SrAxisLe']}}</td>
		     <td align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['SrNearAddLe']}}</td>
		     <td align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['SrVaGlassLe']}}</td>
		     <td align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['SrNvLe']}}</td>
		     {{-- <td align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['PgNearAddLe']}}</td> --}}
		     </tr>
           
        </table></td>
        <td width="35%" align="left" valign="top"><table width="100%" border="0" cellspacing="0" cellpadding="4" class="tdbrd1">
          <tr>
            <td align="center" valign="top" class="txtallvalues">&nbsp;</td>
            <td align="center" valign="top" class="txtallvalues">RE</td>
            <td align="center" valign="top" class="txtallvalues">LE</td>
            {{-- <td align="center" valign="top" class="txtallvalues">AXIS</td>
            <td align="center" valign="top" class="txtallvalues">ADD</td> --}}
          </tr>
          <tr>
            <td width="16%" align="center" valign="top" class="tdbrd">NCT</td>
            <td width="24%" align="center" valign="top" class="tdbrd">{{$opto_data['items']['IopNctRe']}}</td>
            <td width="21%" align="center" valign="top" class="tdbrd">{{$opto_data['items']['IopNctLe']}}</td>
            {{-- <td width="22%" align="center" valign="top" class="tdbrd">&nbsp;</td>
            <td width="17%" align="center" valign="top" class="tdbrd">&nbsp;</td> --}}
          </tr>
          <tr>
            <td align="center" valign="top" class="tdbrd">AT</td>
            <td align="center" valign="top" class="tdbrd">{{$opto_data['items']['IopAtRe2']}}</td>
            <td align="center" valign="top" class="tdbrd">{{$opto_data['items']['IopAtLe2']}}</td>
            {{-- <td align="center" valign="top" class="tdbrd">&nbsp;</td>
            <td align="center" valign="top" class="tdbrd">&nbsp;</td> --}}
          </tr>
          <tr>
            <td align="center" valign="top" class="tdbrd">ROPLAS</td>
            <td align="center" valign="top" class="tdbrd">{{$opto_data['items']['IopAtRe']}}</td>
            <td align="center" valign="top" class="tdbrd">{{$opto_data['items']['IopAtLe']}}</td>
            {{-- <td align="center" valign="top" class="tdbrd">&nbsp;</td>
            <td align="center" valign="top" class="tdbrd">&nbsp;</td> --}}
          </tr>
        </table></td>
      </tr>
    </table></td>
  </tr>

  <br>
  <tr>
    <td align="left" valign="top" class="txtallheading" style="padding-top: 10px;"><strong>Ocular Examination </strong></td>
  </tr>
  <tr>
    <td align="left" valign="top"><table width="100%" border="0" cellspacing="0" cellpadding="3" class="tblborder">
      <tr>
        <td width="14%" class="tdbrd">&nbsp;</td>
        <td width="40%" align="left" valign="top" class="tdbrd"><div align="center"><strong>RE</strong></div></td>
        <td width="46%" align="left" valign="top" class="tdbrd"><div align="center"><strong>LE</strong></div></td>
      </tr>
      @if($consult_data['items']['ReLids']!='' || $consult_data['items']['LeLids']!='')
      <tr>
        <td height="21" align="left" valign="top" class="tdbrd">Lids</td>
        <td align="left" valign="top" class="txtallvalues ocexamfont">{{$consult_data['items']['ReLids']}}</td>
        <td align="left" valign="top" class="txtallvalues ocexamfont">{{$consult_data['items']['LeLids']}}</td>
      </tr>
      @endif
      @if($consult_data['items']['ReConj']!='' || $consult_data['items']['LeConj']!='')
      <tr>
        <td align="left" valign="middle" class="tdbrd">Surface</td>
        <td align="left" valign="top" class="txtallvalues ocexamfont">{{$consult_data['items']['ReConj']}}</td>
        <td align="left" valign="top" class="txtallvalues ocexamfont">{{$consult_data['items']['LeConj']}} </td>
      </tr>
 @endif
      
 @if($consult_data['items']['ReAntChamber']!='' || $consult_data['items']['LeAntChamber']!='')
      <tr>
        <td class="tdbrd">Ant Chamber </td>
        <td align="left" valign="top" class="txtallvalues ocexamfont">{{$consult_data['items']['ReAntChamber']}}</td>
        <td align="left" valign="top" class="txtallvalues ocexamfont">{{$consult_data['items']['LeAntChamber']}}</td>
      </tr>
      @endif
      @if($consult_data['items']['ReLens']!='' || $consult_data['items']['LeLens']!='')
      <tr>
        <td height="30" align="left" valign="middle" class="tdbrd" >Lens</td>
        <td align="left" valign="top" class="txtallvalues ocexamfont">{{$consult_data['items']['ReLens']}}</td>
        <td align="left" valign="top" class="txtallvalues ocexamfont">{{$consult_data['items']['LeLens']}}</td>
      </tr>
@endif
     

      @if($consult_data['items']['ReNlp']!='' || $consult_data['items']['LeNlp']!='')
      <tr>
        <td class="tdbrd">Fundus</td>
        <td align="left" valign="top" class="txtallvalues ocexamfont">{{$consult_data['items']['ReNlp']}}</td>
        <td align="left" valign="top" class="txtallvalues ocexamfont">{{$consult_data['items']['LeNlp']}}</td>
      </tr>
      @endif
    </table></td>
  </tr>

  <tr>
    <td align="left" valign="top" class="alltextfont" style="padding-top: 10px;"><table width="100%" border="0" cellspacing="0" cellpadding="3" class="tblborder">
          <tr>
        <td height="21" align="left" valign="top" class="tdbrd" style="color: #939393;">Diagnosis</td>
        <td align="left" valign="top" class="txtallvalues txtallheading" style="white-space: pre-wrap;font-family: 'Poppins';">{{strtoupper(strtolower($consult_data['items']['Diagnosis']))}}</td>
      </tr>
            <tr>
        <td align="left" valign="middle" class="tdbrd" style="color: #939393;">Advice </td>
        <td align="left" valign="top" class="txtallvalues txtallheading" style="white-space: pre-wrap;font-family: 'Poppins';">{{strtoupper(strtolower($consult_data['items']['Advice']))}}</td>
      </tr>
                              <tr>
        <td width="21%" height="53" align="left" valign="top" class="tdbrd">Treatment</td>
        <td align="left" valign="top" class="tdbrd">
          <table width="100%" border="0" cellspacing="0" cellpadding="0">
       
            @if($consult_treat_data['items']['TreatChk1'] == 1 || $consult_treat_data['items']['TreatChk2'] == 1 || $consult_treat_data['items']['TreatChk3'] == 1 || $consult_treat_data['items']['TreatChk4'] == 1 || $consult_treat_data['items']['TreatChk5'] == 1 || $consult_treat_data['items']['TreatChk6'] == 1 || $consult_treat_data['items']['TreatChk7'] == 1 || $consult_treat_data['items']['TreatChk8'] == 1 || $consult_treat_data['items']['TreatChk9'] == 1 || $consult_treat_data['items']['TreatChk10'] == 1)
                        Rx,
                        @endif
            @if($consult_treat_data['items']['TreatChk1'] == 1)
                                    @if($consult_treat_data['items']['TreatFreq1'] == '8 TAP' || $consult_treat_data['items']['TreatFreq1'] == '6 TAP' ||
                                    $consult_treat_data['items']['TreatFreq1'] == '4 TAP' || $consult_treat_data['items']['TreatFreq1'] == '3 TAP' ||
                                    $consult_treat_data['items']['TreatFreq1'] == '2 TAP' || $consult_treat_data['items']['TreatFreq1'] == '1 TAP')
                                    <tr>
                                        <td align="left" valign="top" class="addrfont"><strong>{{strtoupper(strtolower($consult_treat_data['items']['TreatName1']))}}</strong></td>
                                    </tr>
                                    @php
                                    $TapValue = $consult_treat_data['items']['TreatFreq1'];
                                    $data1 = (new ConsultantController)->getTapValue($TapValue);
                                    @endphp
                                    @foreach($data1 as $result1)
                                    @php $treattapname1 = (new
                                    OptoController)->convertMarathi($consult_treat_data['items']['TreatType1'],$consult_treat_data['items']['TreatDose1'],
                                    $result1->Value,$consult_treat_data['items']['TreatDur1'],$consult_treat_data['items']['TreatEye1'],$consult_treat_data['items']['Language']); @endphp
                                    <tr>
    
                                        <td style="text-align:justify;font-family: Shivaji01;padding-left: 25px;" >
                                            {{$treattapname1}}</td>
                                        @endforeach
                                    </tr>
                                    @else
                                    <tr>
    
                                        <td align="left" valign="top" class="addrfont"><strong>{{strtoupper(strtolower($consult_treat_data['items']['TreatName1']))}}</strong></td>
                                    </tr>
                                    <tr>
                                        <td style="text-align:justify;font-family: Shivaji01;padding-left: 25px;">
                                            {{$TreatName1}}</td>
                                    </tr>
                                    @endif
                                    <!-- <tr>
                                        <td align="left" valign="top">&nbsp;</td>
                                    </tr> -->
                                    @endif
    
                                    @if($consult_treat_data['items']['TreatChk2'] == 1)
                                    @if($consult_treat_data['items']['TreatFreq2'] == '8 TAP' || $consult_treat_data['items']['TreatFreq2'] == '6 TAP' ||
                                    $consult_treat_data['items']['TreatFreq2'] == '4 TAP' || $consult_treat_data['items']['TreatFreq2'] == '3 TAP' ||
                                    $consult_treat_data['items']['TreatFreq2'] == '2 TAP' || $consult_treat_data['items']['TreatFreq2'] == '1 TAP')
                                    <tr>
                                        <td align="left" valign="top" class="addrfont"><strong>{{strtoupper(strtolower($consult_treat_data['items']['TreatName2']))}} </strong></td>
                                    </tr>
                                    @php
                                    $TapValue2 = $consult_treat_data['items']['TreatFreq2'];
                                    $data2 = (new ConsultantController)->getTapValue($TapValue2);
                                    @endphp
                                    @foreach($data2 as $result2)
                                    @php $treattapname2 = (new
                                    OptoController)->convertMarathi($consult_treat_data['items']['TreatType2'],$consult_treat_data['items']['TreatDose2'],
                                    $result2->Value,$consult_treat_data['items']['TreatDur2'],$consult_treat_data['items']['TreatEye2'],$consult_treat_data['items']['Language']); @endphp
                                    <tr>
    
                                        <td style="text-align:justify;font-family: Shivaji01;padding-left: 25px;">
                                            {{$treattapname2}}</td>
                                        @endforeach
                                    </tr>
                                    @else
                                    <tr>
    
                                        <td align="left" valign="top" class="addrfont"><strong>{{strtoupper(strtolower($consult_treat_data['items']['TreatName2']))}} </strong></td>
                                    </tr>
                                    <tr>
                                        <td style="text-align:justify;font-family: Shivaji01;padding-left: 25px;">
                                            {{$TreatName2}}</td>
                                    </tr>
                                    @endif
                                    <!-- <tr>
                                        <td align="left" valign="top">&nbsp;</td>
                                    </tr> -->
                                    @endif
    
    
                                    @if($consult_treat_data['items']['TreatChk3'] == 1)
                                    @if($consult_treat_data['items']['TreatFreq3'] == '8 TAP' || $consult_treat_data['items']['TreatFreq3'] == '6 TAP' ||
                                    $consult_treat_data['items']['TreatFreq3'] == '4 TAP' || $consult_treat_data['items']['TreatFreq3'] == '3 TAP' ||
                                    $consult_treat_data['items']['TreatFreq3'] == '2 TAP' || $consult_treat_data['items']['TreatFreq3'] == '1 TAP')
                                    <tr>
                                        <td align="left" valign="top" class="addrfont"><strong>{{strtoupper(strtolower($consult_treat_data['items']['TreatName3']))}} </td>
                                    </tr>
                                    @php
                                    $TapValue3 = $consult_treat_data['items']['TreatFreq3'];
                                    $data3 = (new ConsultantController)->getTapValue($TapValue3);
                                    @endphp
                                    @foreach($data3 as $result3)
                                    @php $treattapname3 = (new
                                    OptoController)->convertMarathi($consult_treat_data['items']['TreatType3'],$consult_treat_data['items']['TreatDose3'],
                                    $result3->Value,$consult_treat_data['items']['TreatDur3'],$consult_treat_data['items']['TreatEye3'],$consult_treat_data['items']['Language']); @endphp
                                    <tr>
    
                                        <td style="text-align:justify;font-family: Shivaji01;padding-left: 25px;">
                                            {{$treattapname3}}</td>
                                        @endforeach
                                    </tr>
                                    @else
                                    <tr>
    
                                        <td align="left" valign="top" class="addrfont"><strong>{{strtoupper(strtolower($consult_treat_data['items']['TreatName3']))}} </td>
                                    </tr>
                                    <tr>
                                        <td style="text-align:justify;font-family: Shivaji01;padding-left: 25px;">
                                            {{$TreatName3}}</td>
                                    </tr>
                                    @endif
                                    <!-- <tr>
                                        <td align="left" valign="top">&nbsp;</td>
                                    </tr> -->
                                    @endif
    
                                    @if($consult_treat_data['items']['TreatChk4'] == 1)
                                    @if($consult_treat_data['items']['TreatFreq4'] == '8 TAP' || $consult_treat_data['items']['TreatFreq4'] == '6 TAP' ||
                                    $consult_treat_data['items']['TreatFreq4'] == '4 TAP' || $consult_treat_data['items']['TreatFreq4'] == '3 TAP' ||
                                    $consult_treat_data['items']['TreatFreq4'] == '2 TAP' || $consult_treat_data['items']['TreatFreq4'] == '1 TAP')
                                    <tr>
                                        <td align="left" valign="top" class="addrfont"><strong>{{strtoupper(strtolower($consult_treat_data['items']['TreatName4']))}}</strong> </td>
                                    </tr>
                                    @php
                                    $TapValue4 = $consult_treat_data['items']['TreatFreq4'];
                                    $data4 = (new ConsultantController)->getTapValue($TapValue4);
                                    @endphp
                                    @foreach($data4 as $result4)
                                    @php $treattapname4 = (new
                                    OptoController)->convertMarathi($consult_treat_data['items']['TreatType4'],$consult_treat_data['items']['TreatDose4'],
                                    $result4->Value,$consult_treat_data['items']['TreatDur4'],$consult_treat_data['items']['TreatEye4'],$consult_treat_data['items']['Language']); @endphp
                                    <tr>
    
                                        <td style="text-align:justify;font-family: Shivaji01;padding-left: 25px;">
                                            {{$treattapname4}}</td>
                                        @endforeach
                                    </tr>
                                    @else
                                    <tr>
    
                                        <td align="left" valign="top" class="addrfont"><strong>{{strtoupper(strtolower($consult_treat_data['items']['TreatName4']))}}</strong> </td>
                                    </tr>
                                    <tr>
                                        <td style="text-align:justify;font-family: Shivaji01;padding-left: 25px;">
                                            {{$TreatName4}}</td>
                                    </tr>
                                    @endif
                                    <!-- <tr>
                                        <td align="left" valign="top">&nbsp;</td>
                                    </tr> -->
                                    @endif
    
                                    @if($consult_treat_data['items']['TreatChk5'] == 1)
                                    @if($consult_treat_data['items']['TreatFreq5'] == '8 TAP' || $consult_treat_data['items']['TreatFreq5'] == '6 TAP' ||
                                    $consult_treat_data['items']['TreatFreq5'] == '4 TAP' || $consult_treat_data['items']['TreatFreq5'] == '3 TAP' ||
                                    $consult_treat_data['items']['TreatFreq5'] == '2 TAP' || $consult_treat_data['items']['TreatFreq5'] == '1 TAP')
                                    <tr>
                                        <td align="left" valign="top"><strong>{{strtoupper(strtolower($consult_treat_data['items']['TreatName5']))}}</strong></td>
                                    </tr>
                                    @php
                                    $TapValue5 = $consult_treat_data['items']['TreatFreq5'];
                                    $data5 = (new ConsultantController)->getTapValue($TapValue5);
                                    @endphp
                                    @foreach($data5 as $result5)
                                    @php $treattapname5 = (new
                                    OptoController)->convertMarathi($consult_treat_data['items']['TreatType5'],$consult_treat_data['items']['TreatDose5'],
                                    $result5->Value,$consult_treat_data['items']['TreatDur5'],$consult_treat_data['items']['TreatEye5'],$consult_treat_data['items']['Language']); @endphp
                                    <tr>
    
                                        <td style="text-align:justify;font-family: Shivaji01;padding-left: 25px;">
                                            {{$treattapname5}}</td>
                                        @endforeach
                                    </tr>
                                    @else
                                    <tr>
    
                                        <td align="left" valign="top" class="addrfont"><strong>{{strtoupper(strtolower($consult_treat_data['items']['TreatName5']))}}</strong> </td>
                                    </tr>
                                    <tr>
                                        <td style="text-align:justify;font-family: Shivaji01;padding-left: 25px;">
                                            {{$TreatName5}}</td>
                                    </tr>
                                    @endif
                                    <!-- <tr>
                                        <td align="left" valign="top">&nbsp;</td>
                                    </tr> -->
                                    @endif
    
                                    @if($consult_treat_data['items']['TreatChk6'] == 1)
                                    @if($consult_treat_data['items']['TreatFreq6'] == '8 TAP' || $consult_treat_data['items']['TreatFreq6'] == '6 TAP' ||
                                    $consult_treat_data['items']['TreatFreq6'] == '4 TAP' || $consult_treat_data['items']['TreatFreq6'] == '3 TAP' ||
                                    $consult_treat_data['items']['TreatFreq6'] == '2 TAP' || $consult_treat_data['items']['TreatFreq6'] == '1 TAP')
                                    <tr>
                                        <td align="left" valign="top" class="addrfont"><strong>{{strtoupper(strtolower($consult_treat_data['items']['TreatName6']))}}</strong> </td>
                                    </tr>
                                    @php
                                    $TapValue6 = $consult_treat_data['items']['TreatFreq6'];
                                    $data6 = (new ConsultantController)->getTapValue($TapValue6);
                                    @endphp
                                    @foreach($data6 as $result6)
                                    @php $treattapname6 = (new
                                    OptoController)->convertMarathi($consult_treat_data['items']['TreatType6'],$consult_treat_data['items']['TreatDose6'],
                                    $result6->Value,$consult_treat_data['items']['TreatDur6'],$consult_treat_data['items']['TreatEye6'],$consult_treat_data['items']['Language']); @endphp
                                    <tr>
    
                                        <td style="text-align:justify;font-family: Shivaji01;padding-left: 25px;">
                                            {{$treattapname6}}</td>
                                        @endforeach
                                    </tr>
                                    @else
                                    <tr>
    
                                        <td align="left" valign="top" class="addrfont"><strong>{{strtoupper(strtolower($consult_treat_data['items']['TreatName6']))}}</strong> </td>
                                    </tr>
                                    <tr>
                                        <td style="text-align:justify;font-family: Shivaji01;padding-left: 25px;">
                                            {{$TreatName6}}</td>
                                    </tr>
                                    @endif
                                    <!-- <tr>
                                        <td align="left" valign="top">&nbsp;</td>
                                    </tr> -->
                                    @endif
    
                                    @if($consult_treat_data['items']['TreatChk7'] == 1)
                                    @if($consult_treat_data['items']['TreatFreq7'] == '8 TAP' || $consult_treat_data['items']['TreatFreq7'] == '6 TAP' ||
                                    $consult_treat_data['items']['TreatFreq7'] == '4 TAP' || $consult_treat_data['items']['TreatFreq7'] == '3 TAP' ||
                                    $consult_treat_data['items']['TreatFreq7'] == '2 TAP' || $consult_treat_data['items']['TreatFreq7'] == '1 TAP')
                                    <tr>
                                        <td align="left" valign="top" class="addrfont"><strong>{{strtoupper(strtolower($consult_treat_data['items']['TreatName7']))}}</strong></td>
                                    </tr>
                                    @php
                                    $TapValue7 = $consult_treat_data['items']['TreatFreq7'];
                                    $data7 = (new ConsultantController)->getTapValue($TapValue7);
                                    @endphp
                                    @foreach($data7 as $result7)
                                    @php $treattapname7 = (new
                                    OptoController)->convertMarathi($consult_treat_data['items']['TreatType7'],$consult_treat_data['items']['TreatDose7'],
                                    $result7->Value,$consult_treat_data['items']['TreatDur7'],$consult_treat_data['items']['TreatEye7'],$consult_treat_data['items']['Language']); @endphp
                                    <tr>
    
                                        <td style="text-align:justify;font-family: Shivaji01;padding-left: 25px;">
                                            {{$treattapname7}}</td>
                                        @endforeach
                                    </tr>
                                    @else
                                    <tr>
    
                                        <td align="left" valign="top" class="addrfont"><strong>{{strtoupper(strtolower($consult_treat_data['items']['TreatName7']))}}</strong> </td>
                                    </tr>
                                    <tr>
                                        <td style="text-align:justify;font-family: Shivaji01;padding-left: 25px;">
                                            {{$TreatName7}}</td>
                                    </tr>
                                    @endif
                                    <!-- <tr>
                                        <td align="left" valign="top">&nbsp;</td>
                                    </tr> -->
                                    @endif
    
                                    @if($consult_treat_data['items']['TreatChk8'] == 1)
                                    @if($consult_treat_data['items']['TreatFreq8'] == '8 TAP' || $consult_treat_data['items']['TreatFreq8'] == '6 TAP' ||
                                    $consult_treat_data['items']['TreatFreq8'] == '4 TAP' || $consult_treat_data['items']['TreatFreq8'] == '3 TAP' ||
                                    $consult_treat_data['items']['TreatFreq8'] == '2 TAP' || $consult_treat_data['items']['TreatFreq8'] == '1 TAP')
                                    <tr>
                                        <td align="left" valign="top" class="addrfont"><strong>{{strtoupper(strtolower($consult_treat_data['items']['TreatName8']))}}</strong></td>
                                    </tr>
                                    @php
                                    $TapValue8 = $consult_treat_data['items']['TreatFreq8'];
                                    $data8 = (new ConsultantController)->getTapValue($TapValue8);
                                    @endphp
                                    @foreach($data8 as $result8)
                                    @php $treattapname8 = (new
                                    OptoController)->convertMarathi($consult_treat_data['items']['TreatType8'],$consult_treat_data['items']['TreatDose8'],
                                    $result8->Value,$consult_treat_data['items']['TreatDur8'],$consult_treat_data['items']['TreatEye8'],$consult_treat_data['items']['Language']); @endphp
                                    <tr>
    
                                        <td style="text-align:justify;font-family: Shivaji01;padding-left: 25px;">
                                            {{$treattapname8}}</td>
                                        @endforeach
                                    </tr>
                                    @else
                                    <tr>
    
                                        <td align="left" valign="top" class="addrfont"><strong>{{strtoupper(strtolower($consult_treat_data['items']['TreatName8']))}}</strong> </td>
                                    </tr>
                                    <tr>
                                        <td style="text-align:justify;font-family: Shivaji01;padding-left: 25px;">
                                            {{$TreatName8}}</td>
                                    </tr>
                                    @endif
                                    <!-- <tr>
                                        <td align="left" valign="top">&nbsp;</td>
                                    </tr> -->
                                    @endif
    
                                    @if($consult_treat_data['items']['TreatChk9'] == 1)
                                    @if($consult_treat_data['items']['TreatFreq9'] == '8 TAP' || $consult_treat_data['items']['TreatFreq9'] == '6 TAP' ||
                                    $consult_treat_data['items']['TreatFreq9'] == '4 TAP' || $consult_treat_data['items']['TreatFreq9'] == '3 TAP' ||
                                    $consult_treat_data['items']['TreatFreq9'] == '2 TAP' || $consult_treat_data['items']['TreatFreq9'] == '1 TAP')
                                    <tr>
                                        <td align="left" valign="top" class="addrfont"><strong>{{strtoupper(strtolower($consult_treat_data['items']['TreatName9']))}}</strong> </td>
                                    </tr>
                                    @php
                                    $TapValue9 = $consult_treat_data['items']['TreatFreq9'];
                                    $data9 = (new ConsultantController)->getTapValue($TapValue9);
                                    @endphp
                                    @foreach($data9 as $result9)
                                    @php $treattapname9 = (new
                                    OptoController)->convertMarathi($consult_treat_data['items']['TreatType9'],$consult_treat_data['items']['TreatDose9'],
                                    $result9->Value,$consult_treat_data['items']['TreatDur9'],$consult_treat_data['items']['TreatEye9'],$consult_treat_data['items']['Language']); @endphp
                                    <tr>
    
                                        <td style="text-align:justify;font-family: Shivaji01;padding-left: 25px;">
                                            {{$treattapname9}}</td>
                                        @endforeach
                                    </tr>
                                    @else
                                    <tr>
    
                                        <td align="left" valign="top" class="addrfont"><strong>{{strtoupper(strtolower($consult_treat_data['items']['TreatName9']))}}</strong> </td>
                                    </tr>
                                    <tr>
                                        <td style="text-align:justify;font-family: Shivaji01;padding-left: 25px;">
                                            {{$TreatName9}}</td>
                                    </tr>
                                    @endif
                                    <!-- <tr>
                                        <td align="left" valign="top">&nbsp;</td>
                                    </tr> -->
                                    @endif
    
                                    @if($consult_treat_data['items']['TreatChk10'] == 1)
                                    @if($consult_treat_data['items']['TreatFreq10'] == '8 TAP' || $consult_treat_data['items']['TreatFreq10'] == '6 TAP' ||
                                    $consult_treat_data['items']['TreatFreq10'] == '4 TAP' || $consult_treat_data['items']['TreatFreq10'] == '3 TAP' ||
                                    $consult_treat_data['items']['TreatFreq10'] == '2 TAP' || $consult_treat_data['items']['TreatFreq10'] == '1 TAP')
                                    <tr>
                                        <td align="left" valign="top" class="addrfont"><strong>{{strtoupper(strtolower($consult_treat_data['items']['TreatName10']))}}</strong> </td>
                                    </tr>
                                    @php
                                    $TapValue10 = $consult_treat_data['items']['TreatFreq10'];
                                    $data10 = (new ConsultantController)->getTapValue($TapValue10);
                                    @endphp
                                    @foreach($data10 as $result10)
                                    @php $treattapname10 = (new
                                    OptoController)->convertMarathi($consult_treat_data['items']['TreatType10'],$consult_treat_data['items']['TreatDose10'],
                                    $result10->Value,$consult_treat_data['items']['TreatDur10'],$consult_treat_data['items']['TreatEye10'],$consult_treat_data['items']['Language']); @endphp
                                    <tr>
    
                                        <td style="text-align:justify;font-family: Shivaji01;padding-left: 25px;">
                                            {{$treattapname10}}</td>
                                        @endforeach
                                    </tr>
                                    @else
                                    <tr>
    
                                        <td align="left" valign="top" class="addrfont"><strong>{{strtoupper(strtolower($consult_treat_data['items']['TreatName10']))}}</strong> </td>
                                    </tr>
                                    <tr>
                                        <td style="text-align:justify;font-family: Shivaji01;padding-left: 25px;">
                                            {{$TreatName10}}</td>
                                    </tr>
                                    @endif
                                    <!-- <tr>
                                        <td align="left" valign="top">&nbsp;</td>
                                    </tr> -->
                                    @endif
                                    <tr>
                                        <td align="left" valign="top">&nbsp;</td>
                                    </tr>
                                    @if($consult_treat_data['items']['TreatChk1'] == 1 || $consult_treat_data['items']['TreatChk2'] == 1 || $consult_treat_data['items']['TreatChk3'] == 1 || $consult_treat_data['items']['TreatChk4'] == 1 || $consult_treat_data['items']['TreatChk5'] == 1 || $consult_treat_data['items']['TreatChk6'] == 1 || $consult_treat_data['items']['TreatChk7'] == 1 || $consult_treat_data['items']['TreatChk8'] == 1 || $consult_treat_data['items']['TreatChk9'] == 1 || $consult_treat_data['items']['TreatChk10'] == 1)
                                  <tr>
                                    <td><span style="font-size: 9px;line-height: 10px;">Or any other cheaper generic medicine as per choice of patient. <br>
    In case of severe redness,pain,decreased vision or any other emergency,please call <span style="font-size:14px;">8698249356.</span></span></td>
    </tr>
    @endif
                  
            
            </table></td>
      </tr>
          </table></td>
  </tr>

  <table width="700" border="0" cellspacing="0" cellpadding="0" class="alltextfont">
  <tr>
    <td align="left" valign="top">Or any other cheaper generic medicine as per choice of patient.</td>
  </tr>
  <tr>
    <td align="left" valign="top">Ref : </td>
  </tr>
</table>
</table>

            <?php if($opto_data['items']['PrintCrRef'] == 1) {?>
           
            
            <table width="700" border="0" cellspacing="0" cellpadding="4" class="tblborder">
              <tr>
                <td width="10%" align="center" valign="top" class="txtallvalues1" >&nbsp;</td>
                <td width="11%" align="center" valign="top" class="txtallvalues1">SPH</td>
                <td width="11%" align="center" valign="top" class="txtallvalues1">CYL</td>
                <td width="11%" align="center" valign="top" class="txtallvalues1">AXIS</td>
                <td width="11%" align="center" valign="top" class="txtallvalues1">ADD</td>
                <td width="11%" align="center" valign="top" class="txtallvalues1">VISION</td>
                <td width="11%" align="center" valign="top" class="txtallvalues1">NEAR</td>
                {{-- <td width="11%" align="center" valign="top" class="txtallvalues1">PIN HOLE </td> --}}
              </tr>
              <tr>
                <td width="10%" align="center" valign="top" class="txtallvalues1" >RE</td>
                <td width="11%" align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['SrSphRe']}}</td>
                <td width="11%" align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['SrCylRe']}}</td>
                <td width="11%" align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['SrAxisRe']}}</td>
                <td width="11%" align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['SrNearAddRe']}}</td>
                <td width="11%" align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['SrVaGlassRe']}}</td>
                <td width="11%" align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['SrNvRe']}}</td>
                {{-- <td width="11%" align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['PgNearAddLe']}}</td> --}}
              </tr>
           <tr>
             <td align="center" valign="top" class="txtallvalues1" >LE</td>
             <td align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['SrSphLe']}}</td>
             <td align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['SrCylLe']}}</td>
             <td align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['SrAxisLe']}}</td>
             <td align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['SrNearAddLe']}}</td>
             <td align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['SrVaGlassLe']}}</td>
             <td align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['SrNvLe']}}</td>
             {{-- <td align="center" valign="top" class="txtallvalues1">{{$opto_data['items']['PgNearAddLe']}}</td> --}}
             </tr>
            </table>
            <?php } ?>

                    </table>
                </td>
            </tr>

    <!-- Print footer: doctors block (shown at bottom on print) -->
    <div class="divFooter detail-print-footer">
        <table width="100%" border="0" cellspacing="0" cellpadding="0" class="smalltextfont">
            <tr>
                <td align="center" valign="top">
                    <table width="701" border="0" cellpadding="3" cellspacing="0" align="center" class="tblborder">
                        <tr>
                            <td width="20%" align="center" valign="top" class="tblborder">
                                <table width="100%" border="0" cellspacing="0" cellpadding="2" class="smalltextfont">
                                    <tr><td align="center" valign="top">Dr. Satish Deshmukh</td></tr>
                                    <tr><td align="center" valign="top">MBBS, DOMS, MS</td></tr>
                                    <tr><td align="center" valign="top">Reg. No 32183</td></tr>
                                </table>
                            </td>
                            <td width="20%" align="center" valign="top" class="tblborder">
                                <table width="100%" border="0" cellspacing="0" cellpadding="2" class="smalltextfont">
                                    <tr><td align="center" valign="top">Dr. Himanshu Deshmukh</td></tr>
                                    <tr><td align="center" valign="top">MBBS, DOMS, DNB, FMRF, FVRF</td></tr>
                                    <tr><td align="center" valign="top">Reg No. 2003/02/438</td></tr>
                                </table>
                            </td>
                            <td width="20%" align="center" valign="top" class="tblborder">
                                <table width="100%" border="0" cellspacing="0" cellpadding="2" class="smalltextfont">
                                    <tr><td align="center" valign="top">Dr. Bhagyashri Deshmukh</td></tr>
                                    <tr><td align="center" valign="top">MBBS, DNB, MNAMS</td></tr>
                                    <tr><td align="center" valign="top">Reg No. 2005/04/2277</td></tr>
                                </table>
                            </td>
                            <td width="20%" align="center" valign="top" class="tblborder">
                                <table width="100%" border="0" cellspacing="0" cellpadding="2" class="smalltextfont">
                                    <tr><td align="center" valign="top">Dr. Sudesh G. Shendre</td></tr>
                                    <tr><td align="center" valign="top">MBBS, DOMS</td></tr>
                                    <tr><td align="center" valign="top">Reg No. 61757</td></tr>
                                </table>
                            </td>
                            <td width="20%" align="center" valign="top" class="tblborder">
                                <table width="100%" border="0" cellspacing="0" cellpadding="2" class="smalltextfont">
                                    <tr><td align="center" valign="top">Dr. Madhuri</td></tr>
                                    <tr><td align="center" valign="top">MBBS, DOMS</td></tr>
                                    <tr><td align="center" valign="top">Reg No. 2022/02/0368</td></tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
