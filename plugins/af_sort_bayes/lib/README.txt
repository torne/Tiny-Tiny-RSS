/*
  ***** BEGIN LICENSE BLOCK *****
   This file is part of PHP Naive Bayesian Filter.

   The Initial Developer of the Original Code is
   Loic d'Anterroches [loic xhtml.net].
   Portions created by the Initial Developer are Copyright (C) 2003
   the Initial Developer. All Rights Reserved.

   PHP Naive Bayesian Filter is free software; you can redistribute it 
   and/or modify it under the terms of the GNU General Public License as 
   published by the Free Software Foundation; either version 2 of 
   the License, or (at your option) any later version.

   PHP Naive Bayesian Filter is distributed in the hope that it will 
   be useful, but WITHOUT ANY WARRANTY; without even the implied
   warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
   See the GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with Foobar; if not, write to the Free Software
   Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

  ***** END LICENSE BLOCK *****
*/


** Presentation **

Voici une implementation generale d'un filtre reposant sur le theoreme de Bayes.
L'application la plus connue est le filtre anti-spam. Vous pouvez aussi 
l'utiliser pour faire de la classification automatique de documents.

Ce programme se base sur la version simplifiee du theoreme de Bayes comme 
decrite par Ken Williams, ken@mathforum.org sur la page
http://mathforum.org/~ken/bayes/bayes.html au 31/10/2003. 

Le systeme permet de maniere generale de faire la classification de documents 
textes dans differentes categories. Si vous voulez l'utiliser pour une 
classification de vos messages entre spam et non-spam, alors il vous faudra 2 
categories, une "spam" et une "nonspam". 

J'ai cree ce script car c'est une sujet a la mode en ce moment. Particulierement
pour filtrer les commentaires et les trackbacks dans les blogs. Le systeme 
propose ici permet d'avoir plus que deux categories spam et non spam. Cela permet
donc theoriquement de l'utiliser pour la classification dans de multiples
categories.

Un petit script 'index.php' vous permet de tester le systeme, ensuite vous
pouvez inclure la classe dans vos scripts. Les fichiers class.naivebayesian.php
et class.naivebayesianstorage.php peuvent aussi etre utilises avec la licence
GNU Lesser General Public License Version 2.1 ou ulterieure.


** Fonctionnalites **

- Une classe avec la logique de base, une autre qui est l'interface de stockage.
- Stockage des donnees dans une base de données pour le moment MySQL mais
vous pouvez utiliser celle que vous voulez via l'interface de stockage.
- Apprentissage
- Desapprentissage
- Archivage automatique des documents "reference"
- L'interface de stockage par defaut utilise MySQL et repose sur deux classes
d'Olivier Meunier.

** Utilisation **

Regardez le code de index.php
Pour une bonne utilisation il vous faut creer une autre classe qui herite de
NaiveBayesian pour avoir votre propre fonction pour ignorer les mots qui ne
portent pas de sens particulier. Ceci n'est pas fait dans 'index.php'

class votreclass extends NaiveBayesian 
{
    function getIgnoreList()
    {
    	return array('the', 'that', 'you', 'for', 'and');
    }
}


** Des questions **

Pouvez me contacter par email a loic xhtml.net, ou venir sur http://www.xhtml.net/


