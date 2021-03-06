<?php

namespace Kaigan\QBank2\API;

use \Kaigan\QBank2\API\Exception\CommunicationException;
use \Kaigan\QBank2\API\Exception\ConnectionException;
use \Kaigan\QBank2\API\Model\Folder;
use \Kaigan\QBank2\API\Model\Property;
use \Kaigan\QBank2\API\Model\SimpleFolder;
use \Kaigan\QBank2\API\Model\SimpleObject;
	
/**
 * Provides functionality for folders in QBank.
 * @author Björn Hjortsten
 * @copyright Kaigan 2010
 */
class FolderAPI extends BaseAPI {

	/**
	 * Gets folders from QBank.
	 * Default is to get all folders.
	 * @param int $rootFolderId The folder to consider as root.
	 * @param int $depth How many levels of folders to get.
	 * @param bool $hierarchical If set to true, will return a hierachial list of {@link Folder}s.
	 * @throws ConnectionException Thrown if something went wrong with the connection.
	 * @throws CommunicationException Thrown if something went wrong while getting the folders.
	 * @author Björn Hjortsten
	 * @return mixed An array of {@link SimpleFolder}s or an array of top level {@link Folder}s if $hierarchial is set to true. Null if there are no results.
	 */
	public function getFolders($rootFolderId = null, $depth = null, $hierarchical = false) {
		if ($rootFolderId == null) {
			$rootFolderId = 0;
		}
		if ($depth == null) {
			// Since there is no way to fetch for an infinite depth, we fetch for the maximum logical depth
			$depth = 23;
		}
		$data = array('folderId' => $rootFolderId, 'depth' => $depth, 'fetchProperties' => $hierarchical);
		$result = $this->call('getfolderstructure', $data);
		if (is_array($result->data)) {
			foreach ($result->data as $folder) {
				if ($hierarchical === true) {
					$properties = array();
					foreach ($folder->properties as $property) {
						$properties[] = Property::createFromRawObject($property);
					}
					$folders[$folder->folderId] = new Folder($folder->name, $folder->tree, $folder->owner, strtotime($folder->created), strtotime($folder->updated), $properties);
				} else {
					$folders[$folder->folderId] = new SimpleFolder($folder->name, $folder->tree, $folder->owner, strtotime($folder->created), strtotime($folder->updated));
				}
			}
		} else {
			return null;
		}
		if ($hierarchical === true && is_array($folders)) {
			$folders = Folder::createTree($folders);
		}
		return $folders;
	}

	/**
	 * Gets a folder from QBank.
	 * @param int $id The folders id.
	 * @param bool $simple If true, gets {@link SimpleFolder}s, otherwise a {@link Folder}.
	 * @param bool $recursive If the folders subfolders also should be returned.
	 * @throws CommunicationException Thrown if something went wrong while getting the folder.
	 * @throws ConnectionException Thrown if something went wrong with the connection.
	 * @author Björn Hjortsten
	 * @return mixed If $simple is TRUE and it may return an array of {@link SimpleFolder}s or a {@link SimpleFolder} depending on $recursive. If $simple is FALSE it will return a {@link Folder}.
	 */
	public function getFolder($id, $simple = true, $recursive = false) {
		if ($recursive === true) {
			$calls[] = array('name' => 'subfolders', 'function' => 'getfolderstructure', 'arguments' => array('folderId' => $id, 'depth' => 23, 'fetchProperties' => !$simple));
		}
		$calls[] = array('name' => 'folder', 'function' => 'getfolderinformation', 'arguments' => array('folderId' => $id));
		$result = $this->call('batch', array('calls' => $calls));
		if ($result->results->folder->success !== true) {
			$this->wrapperLog->error('Error while getting folder: '.$result->results->folder->error->message, array(
				'code' => $result->results->folder->error->code,
				'type' => $result->results->folder->error->type
			));
			throw new CommunicationException($result->results->folder->error->message, $result->results->folder->error->code, $result->results->folder->error->type);
		}
		$folder = $result->results->folder->folder;
		if ($simple === true) {
			$folder = new SimpleFolder($folder->name, $folder->tree, $folder->owner, strtotime($folder->created), strtotime($folder->updated));
		} else {
			$properties = array();
			foreach ($folder->properties as $property) {
				$properties[] = Property::createFromRawObject($property);
			}
			$folder = new Folder($folder->name, $folder->tree, $folder->owner, strtotime($folder->created), strtotime($folder->updated), $properties);
		}
		if ($recursive === true) {
			if ($result->results->subfolders->success !== true) {
				$this->wrapperLog->error('Error while getting folder: '.$result->results->subfolders->error->message, array(
					'code' => $result->results->subfolders->error->code,
					'type' => $result->results->subfolders->error->type
				));
				throw new CommunicationException($result->results->subfolders->error->message, $result->results->subfolders->error->code, $result->results->subfolders->error->type);
			}
			if (is_array($result->results->subfolders->data)) {
				foreach ($result->results->subfolders->data as $subfolder) {
					if ($simple === true) {
						$folders[$subfolder->folderId] = new SimpleFolder($subfolder->name, $subfolder->tree, $subfolder->owner, strtotime($subfolder->created),
																		  strtotime($subfolder->updated));
					} else {
						$properties = array();
						foreach ($subfolder->properties as $property) {
							$properties[] = Property::createFromRawObject($property);
						}
						$folders[$subfolder->folderId] = new Folder($subfolder->name, $subfolder->tree, $subfolder->owner, strtotime($subfolder->created), strtotime($subfolder->updated),
																	$properties);
					}
				}
			}
			$folders[$folder->getId()] = $folder;
			if ($simple === false) {
				$folders = Folder::createTree($folders);
				return $folders[$folder->getId()];
			}
			$folder = $folders;
		}
		return $folder;
	}

	/**
	 * Gets all the folders an object exists in.
	 * @param SimpleObject $object The object to get the folders for.
	 * @author Björn Hjortsten
	 * @throws CommunicationException Thrown if something went went wrong getting the folders.
	 * @throws ConnectionException Thrown if something went wrong with the connection.
	 * @return array An array of {@link SimpleFolder}s.
	 */
	public function getFoldersByObject(SimpleObject $object) {
		$result = $this->call('getfoldersbyobjectid', array('objectId' => $object->getId()));
		if (is_array($result->folders)) {
			$folders = array();
			foreach ($result->folders as $rawFolder) {
				$folders[] = new SimpleFolder($rawFolder->name, $rawFolder->tree, $rawFolder->owner, strtotime($rawFolder->created), strtotime($rawFolder->updated));
			}
			return $folders;
		} else {
			return array();
		}
	}

	/**
	 * Creates a folder in QBank.
	 * @param string $name The name of the new folder.
	 * @param int $parentFolderId The id of the new folders parent folder.
	 * @param int $folderType The type of folder to create.
	 * @throws CommunicationException Thrown if something went went wrong while creating the folder.
	 * @throws ConnectionException Thrown if something went wrong with the connection.
	 * @author Björn Hjortsten
	 * @return SimpleFolder The new folder.
	 */
	public function createFolder($name, $parentFolderId = 0, $folderType = 1) {
		$calls[] = array('name' => 'creation', 'function' => 'createfolder', 'arguments' => array('name' => $name, 'parentId' => $parentFolderId, 'folderType' => $folderType));
		$calls[] = array('name' => 'folder', 'function' => 'getfolderinformation', 'arguments' => array('folderId' => '$creation.folderId'));
		$result = $this->call('batch', array('calls' => $calls));
		if ($result->results->creation->success !== true) {
			$this->wrapperLog->error('Error while creating folder: '.$result->results->creation->error->message, array(
				'code' => $result->results->creation->error->code,
				'type' => $result->results->creation->error->type
			));
			throw new CommunicationException($result->results->creation->error->message, $result->results->creation->error->code, $result->results->creation->error->type);
		}
		if ($result->results->folder->success !== true) {
			$this->wrapperLog->error('Error while creating folder: '.$result->results->folder->error->message, array(
				'code' => $result->results->folder->error->code,
				'type' => $result->results->folder->error->type
			));
			throw new CommunicationException($result->results->folder->error->message, $result->results->folder->error->code, $result->results->folder->error->type);
		}
		$folder = $result->results->folder->folder;
		return new SimpleFolder($folder->name, $folder->tree, $folder->owner, strtotime($folder->created), strtotime($folder->updated));
	}

	/**
	 * Deletes a folder from QBank.
	 * @param int $folderId The id of the folder to be deleted.
	 * @throws ConnectionException Thrown if something went wrong with the connection.
	 * @author Björn Hjortsten
	 * @return bool TRUE if it was deleted, FALSE if not.
	 */
	public function deleteFolder($folderId) {
		try {
			$result = $this->call('deleteFolder', array('folderId' => $folderId));
		} catch (CommunicationException $ce) {
			return false;
		}
		return $result->success;
	}

	/**
	 * Edits a folder in QBank.
	 * @param int $folderId The id of the folder to be edited.
	 * @param string $name The new name of the folder.
	 * @param array $properties A key=>value array where the key is the system name.
	 * @throws CommunicationException Thrown if something went went wrong while editing the folder.
	 * @throws ConnectionException Thrown if something went wrong with the connection.
	 * @author Björn Hjortsten
	 * @return SimpleFolder The changed folder.
	 */
	public function editFolder($folderId, $name, array $properties = array()) {
		$calls[] = array('name' => 'edit', 'function' => 'editfolder', 'arguments' => array('folderId' => $folderId, 'name' => $name, 'properties' => $properties));
		$calls[] = array('name' => 'folder', 'function' => 'getfolderinformation', 'arguments' => array('folderId' => $folderId));
		$result = $this->call('batch', array('calls' => $calls));
		if ($result->results->edit->success !== true) {
			$this->wrapperLog->error('Error while editing folder: '.$result->results->edit->error->message, array(
				'code' => $result->results->edit->error->code,
				'type' => $result->results->edit->error->type
			));
			throw new CommunicationException($result->results->edit->error->message, $result->results->edit->error->code, $result->results->edit->error->type);
		}
		if ($result->results->folder->success !== true) {
			$this->wrapperLog->error('Error while editing folder: '.$result->results->folder->error->message, array(
				'code' => $result->results->folder->error->code,
				'type' => $result->results->folder->error->type
			));
			throw new CommunicationException($result->results->folder->error->message, $result->results->folder->error->code, $result->results->folder->error->type);
		}
		$folder = $result->results->folder->folder;
		return new SimpleFolder($folder->name, $folder->tree, $folder->owner, strtotime($folder->created), strtotime($folder->updated));
	}

	/**
	 * Adds an {@link Object} to a {@link Folder}.
	 * @param int $folderId The id of the folder.
	 * @param int $objectId The id of the object.
	 * @throws CommunicationException Thrown if something went went wrong while adding the object to the folder.
	 * @throws ConnectionException Thrown if something went wrong with the connection.
	 * @author Björn Hjortsten
	 * @return bool True if the object was added, false if not.
	 */
	public function addObjectToFolder($folderId, $objectId) {
		$data['folderId'] = $folderId;
		$data['objectId'] = $objectId;
		try {
			$result = $this->call('addobjectTofolder', $data);
			return $result->success;
		} catch (CommunicationException $ce) {
			if ($ce->getCode() == 99) {
				return false;
			}
			$this->wrapperLog->error('Error while adding object to folder: '.$ce->getMessage());
			throw $ce;
		}
	}

	/**
	 * Removes an {@link Object} from a {@link Folder}.
	 * @param int $folderId The id of the folder.
	 * @param int $objectId The id of the object.
	 * @throws CommunicationException Thrown if something went went wrong while removing the object to the folder.
	 * @throws ConnectionException Thrown if something went wrong with the connection.
	 * @author Björn Hjortsten
	 * @return bool True if the object was removed, false if not.
	 */
	public function removeObjectFromFolder($folderId, $objectId) {
		$data['folderId'] = $folderId;
		$data['objectId'] = $objectId;
		try {
			$result = $this->call('removeobjectfromfolder', $data);
			return $result->success;
		} catch (CommunicationException $ce) {
			if ($ce->getCode() == 99) {
				return false;
			}
			$this->wrapperLog->error('Error while removing object from folder: '.$ce->getMessage());
			throw $ce;
		}
	}
}
