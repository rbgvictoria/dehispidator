#
# -*- coding: utf-8 -*-
'''
$RCSfile: index.py,v $
$Revision: 1217 $
$Author: markus $
$Date: 2012-11-09 12:30:58 +0100 (Fr, 09. Nov 2012) $
'''

# dictionary format:
    # key = sourceNamespace
    # value = tuple (targetNamespace, targetRawCMFile, upgradeFile)
index={
    'http://www.tdwg.org/schemas/abcd/1.2':
        [
          ('http://www.tdwg.org/schemas/abcd/2.06', 'cmf_ABCD_2.06.xml', 'abcd1_2-abcd2_06.txt'),
        ],        
    'http://www.tdwg.org/schemas/abcd/2.06':
        [
          ('http://www.tdwg.org/schemas/abcd/2.06',   'cmf_ABCD_2.06b.xml', 'abcd2_06-abcd2_06.txt'),
          ('http://www.tdwg.org/schemas/abcd/2.06',   'cmf_ABCD_AVH.xml', 'abcd2_06-abcd2_06.txt'),
          ('http://www.tdwg.org/schemas/abcd/2.06',   'cmf_ABCDEFG_2.06.xml', 'abcd2_06-abcd2_06.txt'),
          ('http://www.tdwg.org/schemas/abcd/2.06',   'cmf_ABCDDNA_2.06.xml', 'abcd2_06-abcd2_06.txt'),
          ('http://www.chah.org.au/schemas/hispid/5', 'cmf_HISPID_5.xml', 'abcd2_06-abcd2_06.txt'),
          ('http://www.tdwg.org/schemas/abcd/1.2',    'cmf_ABCD_1.20.xml','abcd2_06-abcd1_2.txt'),
        ],
    'http://www.chah.org.au/schemas/hispid/5':
        [
          ('http://www.tdwg.org/schemas/abcd/2.06',   'cmf_ABCD_2.06.xml', 'abcd2_06-abcd2_06.txt'),
          ('http://www.tdwg.org/schemas/abcd/2.06',   'cmf_ABCD_2.06b.xml', 'abcd2_06-abcd2_06.txt'),
          ('http://www.tdwg.org/schemas/abcd/2.06',   'cmf_ABCD_AVH.xml', 'abcd2_06-abcd2_06.txt'),
          ('http://www.tdwg.org/schemas/abcd/2.06',   'cmf_ABCDEFG_2.06.xml', 'abcd2_06-abcd2_06.txt'),
          ('http://www.tdwg.org/schemas/abcd/2.06',   'cmf_ABCDDNA_2.06.xml', 'abcd2_06-abcd2_06.txt'),
        ],
    'http://www.ipgri.cgiar.org/schemas/mcpd/1.00':
        [
          ('http://www.tdwg.org/schemas/abcd/2.06', 'cmf_ABCD_2.06.xml', 'mcpd-abcd2_06.txt'),
        ],        
    'http://digir.net/schema/conceptual/darwin/2003/1.0':
        [
          ('http://www.tdwg.org/schemas/abcd/2.06', 'cmf_ABCD_2.06.xml', 'dwc2-abcd2_06.txt'),
        ],        
    'http://www.ipgri.org/schemas/gcp_pass/1.02':
        [
          ('http://www.ipgri.org/schemas/gcp_pass/1.03', 'cmf_gcp_passport_1.03.xml', 'gcp_passport_v102-gcp_passport_v103.txt'),
        ],        
    'http://www.ipgri.cgiar.org/schemas/mcpd/1.00':
        [
          ('http://www.ipgri.org/schemas/gcp_pass/1.03', 'cmf_gcp_passport_1.03.xml', 'mcpd-gcp_passport_1_03.txt'),
        ],        
    'http://www.ipgri.org/schemas/gcp_pass/1.02':
        [
          ('http://www.ipgri.org/schemas/gcp_passport_1.04', 'cmf_gcp_passport_1.04.xml', 'upgrade_gcp_passport_1.02_to_1.04.txt'),
        ],        
    'http://www.ipgri.org/schemas/gcp_pass/1.03':
        [
          ('http://www.ipgri.org/schemas/gcp_passport_1.04', 'cmf_gcp_passport_1.04.xml', 'upgrade_gcp_passport_1.03_to_1.04.txt'),
        ],        
    'http://www.ipgri.org/schemas/gcp_passport_1.04':
        [
          ('http://www.tdwg.org/schemas/abcd/2.06', 'cmf_ABCD_2.06.xml', 'upgrade_gcp_passport_1.04_to_abcd_2.06.txt'),
        ],        
}
